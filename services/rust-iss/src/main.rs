mod cache;
mod clients;
mod config;
mod domain;
mod error;
mod handlers;
mod repo;
mod routes;
mod services;
mod validation;
mod tests;

use config::Config;
use handlers::AppState;
use sqlx::postgres::PgPoolOptions;
use std::time::Duration;
use tracing::{error, info};
use tracing_subscriber::EnvFilter;

#[tokio::main]
async fn main() -> anyhow::Result<()> {
    // Инициализация логирования
    let subscriber = tracing_subscriber::FmtSubscriber::builder()
        .with_env_filter(EnvFilter::from_default_env())
        .finish();
    let _ = tracing::subscriber::set_global_default(subscriber);

    // Загрузить конфиг
    let config = Config::from_env();
    info!("Config loaded");

    // Инициализировать пул БД с оптимизированными параметрами
    let pool = PgPoolOptions::new()
        .max_connections(config.db_pool_size.unwrap_or(20))
        .min_connections(config.db_min_idle.unwrap_or(5))
        .acquire_timeout(Duration::from_secs(30))
        .idle_timeout(Duration::from_secs(600))
        .max_lifetime(Duration::from_secs(1800))
        .connect(&config.database_url)
        .await?;

    init_db(&pool).await?;
    info!("Database initialized");

    // Создать HTTP клиент
    let api_client = clients::ApiClient::new(config.clone())?;
    info!("HTTP client created");

    // Создать Redis кэш клиент
    let cache_client = cache::CacheClient::new(&config.redis_url, 3600).await?;
    info!("Redis cache client created");

    // Создать App State (DI контейнер)
    let state = AppState::new(pool.clone(), api_client, cache_client).await;

    // ============ Фоновые задачи ============

    // ISS фоновый сбор
    {
        let state = state.clone();
        let interval = config.iss_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = state.iss_service.fetch_and_save().await {
                    error!("ISS fetch error: {}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // OSDR фоновый сбор
    {
        let state = state.clone();
        let interval = config.fetch_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = state.osdr_service.sync().await {
                    error!("OSDR sync error: {}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // APOD фоновое обновление
    {
        let state = state.clone();
        let interval = config.apod_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = state.space_service.refresh_source("apod").await {
                    error!("APOD refresh error: {}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // NEO фоновое обновление
    {
        let state = state.clone();
        let interval = config.neo_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = state.space_service.refresh_source("neo").await {
                    error!("NEO refresh error: {}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // DONKI Flare Events фоновое обновление
    {
        let state = state.clone();
        let interval = config.donki_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = state.space_service.refresh_source("flr").await {
                    error!("DONKI FLR refresh error: {}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // DONKI CME Events фоновое обновление
    {
        let state = state.clone();
        let interval = config.donki_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = state.space_service.refresh_source("cme").await {
                    error!("DONKI CME refresh error: {}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // SpaceX Next Launch фоновое обновление
    {
        let state = state.clone();
        let interval = config.spacex_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = state.space_service.refresh_source("spacex").await {
                    error!("SpaceX refresh error: {}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // JWST фоновое обновление
    {
        let state = state.clone();
        let interval = config.jwst_every_seconds;
        tokio::spawn(async move {
            loop {
                if let Err(e) = state.space_service.refresh_source("jwst").await {
                    error!("JWST refresh error: {}", e);
                }
                tokio::time::sleep(Duration::from_secs(interval)).await;
            }
        });
    }

    // ============ HTTP Server ============

    let app = routes::create_router(state, config.clone());

    let listener = tokio::net::TcpListener::bind(("0.0.0.0", 3000)).await?;
    info!("🚀 Server listening on 0.0.0.0:3000");

    // Graceful shutdown
    let shutdown = async {
        let ctrl_c = async {
            tokio::signal::ctrl_c()
                .await
                .expect("failed to install Ctrl+C handler");
        };

        #[cfg(unix)]
        let terminate = async {
            tokio::signal::unix::signal(tokio::signal::unix::SignalKind::terminate())
                .expect("failed to install signal handler")
                .recv()
                .await;
        };

        #[cfg(not(unix))]
        let terminate = std::future::pending::<()>();

        tokio::select! {
            _ = ctrl_c => {},
            _ = terminate => {},
        }

        info!("Shutdown signal received, starting graceful shutdown...");
    };

    axum::serve(listener, app.into_make_service())
        .with_graceful_shutdown(shutdown)
        .await?;

    info!("Server stopped gracefully");
    Ok(())
}

/// Инициализация БД
async fn init_db(pool: &sqlx::PgPool) -> anyhow::Result<()> {
    // ISS Fetch Log
    sqlx::query(
        "CREATE TABLE IF NOT EXISTS iss_fetch_log(
            id BIGSERIAL PRIMARY KEY,
            fetched_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            source_url TEXT NOT NULL,
            payload JSONB NOT NULL
        )",
    )
    .execute(pool)
    .await?;

    // OSDR Items
    sqlx::query(
        "CREATE TABLE IF NOT EXISTS osdr_items(
            id BIGSERIAL PRIMARY KEY,
            dataset_id TEXT UNIQUE,
            title TEXT,
            status TEXT,
            updated_at TIMESTAMPTZ,
            inserted_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            raw JSONB NOT NULL
        )",
    )
    .execute(pool)
    .await?;

    sqlx::query(
        "CREATE INDEX IF NOT EXISTS ix_osdr_dataset_id ON osdr_items(dataset_id) WHERE dataset_id IS NOT NULL",
    )
    .execute(pool)
    .await?;

    // Space Cache
    sqlx::query(
        "CREATE TABLE IF NOT EXISTS space_cache(
            id BIGSERIAL PRIMARY KEY,
            source TEXT NOT NULL,
            fetched_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            payload JSONB NOT NULL
        )",
    )
    .execute(pool)
    .await?;

    sqlx::query(
        "CREATE INDEX IF NOT EXISTS ix_space_cache_source ON space_cache(source, fetched_at DESC)",
    )
    .execute(pool)
    .await?;

    Ok(())
}