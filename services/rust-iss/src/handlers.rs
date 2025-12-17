use crate::domain::ApiResponse;
use crate::error::ApiError;
use crate::services::*;
use crate::validation::*;
use axum::extract::{Path, Query, State};
use axum::Json;
use serde_json::json;
use sqlx::PgPool;
use validator::Validate;

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
        let iss = IssService::new(pool.clone(), client.clone(), cache.clone());
        let osdr = OsdrService::new(pool.clone(), client.clone(), cache.clone());
        let space = SpaceService::new(pool.clone(), client, cache.clone());

        Self {
            pool,
            cache,
            iss_service: iss,
            osdr_service: osdr,
            space_service: space,
        }
    }
}

// ============ Root & Health Handlers ============

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

/// Корневой handler, чтобы `/` не отдавал 404
pub async fn root_handler() -> Json<ApiResponse<serde_json::Value>> {
    Json(ApiResponse::success(json!({
        "service": "rust_iss",
        "status": "ok",
        "endpoints": [
            "/health",
            "/last",
            "/iss/trend",
            "/osdr/list",
            "/space/summary"
        ]
    })))
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
    Query(params): Query<std::collections::HashMap<String, String>>,
) -> Result<Json<ApiResponse<serde_json::Value>>, ApiError> {
    // Валидация параметров
    let limit = params.get("limit")
        .and_then(|s| s.parse::<i64>().ok())
        .filter(|&l| l > 0 && l <= 100);
    
    let osdr_params = OsdrQueryParams { 
        limit, 
        status: params.get("status").cloned(),
        search: params.get("search").cloned(),
        sort_by: params.get("sort_by").cloned(),
        sort_order: params.get("sort_order").cloned(),
    };
    osdr_params.validate()
        .map_err(|e| ApiError::bad_request("VALIDATION_ERROR", format!("Invalid parameters: {}", e)))?;
    
    let items = state.osdr_service.list(limit).await?;
    Ok(Json(ApiResponse::success(json!({"items": items}))))
}

// ============ Space Cache Handlers ============

pub async fn space_latest_handler(
    Path(src): Path<String>,
    State(state): State<AppState>,
) -> Result<Json<ApiResponse<serde_json::Value>>, ApiError> {
    // Валидация source ID
    validate_source_id(&src)
        .map_err(|e| ApiError::bad_request("INVALID_SOURCE", e))?;
    
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
