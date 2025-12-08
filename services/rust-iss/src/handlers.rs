use crate::domain::ApiResponse;
use crate::error::ApiError;
use crate::services::*;
use axum::extract::{Path, State};
use axum::Json;
use serde_json::json;
use sqlx::PgPool;

/// App State - DI контейнер
#[derive(Clone)]
pub struct AppState {
    pub pool: PgPool,
    pub cache: crate::cache::CacheClient,
    pub iss_service: IssService,
    pub osdr_service: OsdrService,
    pub space_service: SpaceService,
}

impl AppState {
    pub async fn new(pool: PgPool, client: crate::clients::ApiClient, cache: crate::cache::CacheClient) -> Self {
        let iss = IssService::new(pool.clone(), client.clone());
        let osdr = OsdrService::new(pool.clone(), client.clone());
        let space = SpaceService::new(pool.clone(), client);

        Self {
            pool,
            cache,
            iss_service: iss,
            osdr_service: osdr,
            space_service: space,
        }
    }
}

// ============ Health Handler ============

#[derive(serde::Serialize)]
pub struct HealthResponse {
    pub status: String,
    pub now: chrono::DateTime<chrono::Utc>,
}

pub async fn health_handler() -> Json<ApiResponse<HealthResponse>> {
    Json(ApiResponse::success(HealthResponse {
        status: "ok".to_string(),
        now: chrono::Utc::now(),
    }))
}

// ============ ISS Handlers ============

pub async fn iss_last_handler(
    State(state): State<AppState>,
) -> Result<Json<ApiResponse<serde_json::Value>>, ApiError> {
    let data = state.iss_service.get_last().await?;

    match data {
        Some(log) => Ok(Json(ApiResponse::success(serde_json::json!(log)))),
        None => Ok(Json(ApiResponse::success(json!({"message": "no data"})))),
    }
}

pub async fn iss_fetch_handler(
    State(state): State<AppState>,
) -> Result<Json<ApiResponse<serde_json::Value>>, ApiError> {
    let log = state.iss_service.fetch_and_save().await?;
    Ok(Json(ApiResponse::success(serde_json::json!(log))))
}

pub async fn iss_trend_handler(
    State(state): State<AppState>,
) -> Result<Json<ApiResponse<crate::domain::IssTrend>>, ApiError> {
    let trend = state.iss_service.get_trend().await?;
    Ok(Json(ApiResponse::success(trend)))
}

// ============ OSDR Handlers ============

pub async fn osdr_sync_handler(
    State(state): State<AppState>,
) -> Result<Json<ApiResponse<serde_json::Value>>, ApiError> {
    let written = state.osdr_service.sync().await?;
    Ok(Json(ApiResponse::success(json!({"written": written}))))
}

pub async fn osdr_list_handler(
    State(state): State<AppState>,
) -> Result<Json<ApiResponse<serde_json::Value>>, ApiError> {
    let items = state.osdr_service.list(None).await?;
    Ok(Json(ApiResponse::success(json!({"items": items}))))
}

// ============ Space Cache Handlers ============

pub async fn space_latest_handler(
    Path(src): Path<String>,
    State(state): State<AppState>,
) -> Result<Json<ApiResponse<serde_json::Value>>, ApiError> {
    let cache = state.space_service.get_latest(&src).await?;

    match cache {
        Some(c) => Ok(Json(ApiResponse::success(json!({
            "source": src,
            "fetched_at": c.fetched_at,
            "payload": c.payload
        })))),
        None => Ok(Json(ApiResponse::success(json!({
            "source": src,
            "message": "no data"
        })))),
    }
}

pub async fn space_refresh_handler(
    State(state): State<AppState>,
) -> Result<Json<ApiResponse<serde_json::Value>>, ApiError> {
    let sources = vec!["apod", "neo", "flr", "cme", "spacex"];
    let mut refreshed = Vec::new();

    for src in sources {
        if let Err(e) = state.space_service.refresh_source(src).await {
            tracing::warn!("Failed to refresh {}: {}", src, e);
            continue;
        }
        refreshed.push(src);
    }

    Ok(Json(ApiResponse::success(json!({"refreshed": refreshed}))))
}

pub async fn space_summary_handler(
    State(state): State<AppState>,
) -> Result<Json<ApiResponse<serde_json::Value>>, ApiError> {
    let summary = state.space_service.summary().await?;
    Ok(Json(ApiResponse::success(summary)))
}
