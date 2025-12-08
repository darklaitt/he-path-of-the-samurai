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

    // Инициализировать пул БД
    let pool = PgPoolOptions::new()
        .max_connections(10)
        .acquire_timeout(Duration::from_secs(5))
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

    // ============ HTTP Server ============

    let app = routes::create_router(state);

    let listener = tokio::net::TcpListener::bind(("0.0.0.0", 3000)).await?;
    info!("🚀 Server listening on 0.0.0.0:3000");

    axum::serve(listener, app.into_make_service()).await?;

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