use crate::handlers::*;
use crate::config::Config;
use axum::{
    extract::Request,
    middleware::Next,
    response::{IntoResponse, Response},
    routing::get,
    Router,
};
use tower::ServiceBuilder;
use std::sync::Arc;
use tokio::sync::Semaphore;

pub fn create_router(state: AppState, config: Config) -> Router {
    // Rate limiting через Semaphore
    let rate_limiter = Arc::new(Semaphore::new(config.rate_limit_requests as usize));
    
    Router::new()
        // Root → краткая информация о сервисе
        .route("/", get(root_handler))
        // Health check
        .route("/health", get(health_handler))
        
        // ISS endpoints
        .route("/last", get(iss_last_handler))
        .route("/fetch", get(iss_fetch_handler))
        .route("/iss/trend", get(iss_trend_handler))
        
        // OSDR endpoints
        .route("/osdr/sync", get(osdr_sync_handler))
        .route("/osdr/list", get(osdr_list_handler))
        
        // Space cache endpoints
        .route("/space/:src/latest", get(space_latest_handler))
        .route("/space/refresh", get(space_refresh_handler))
        .route("/space/summary", get(space_summary_handler))
        
        .layer(
            ServiceBuilder::new()
                // Rate limiting middleware
                .layer(axum::middleware::from_fn_with_state(
                    rate_limiter,
                    rate_limit_middleware,
                ))
                .into_inner(),
        )
        .with_state(state)
}

/// Rate limiting middleware
async fn rate_limit_middleware(
    axum::extract::State(limiter): axum::extract::State<Arc<Semaphore>>,
    request: Request,
    next: Next,
) -> Response {
    // Пропускаем health check без ограничений
    if request.uri().path() == "/health" {
        return next.run(request).await;
    }
    
    // Пытаемся получить permit
    match limiter.try_acquire() {
        Ok(_permit) => {
            next.run(request).await
        }
        Err(_) => {
            // Rate limit exceeded
            crate::error::ApiError::rate_limit().into_response()
        }
    }
}
