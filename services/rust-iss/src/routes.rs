use crate::handlers::*;
use axum::{
    routing::{get, post},
    Router,
};

pub fn create_router(state: AppState) -> Router {
    Router::new()
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
        
        .with_state(state)
}
