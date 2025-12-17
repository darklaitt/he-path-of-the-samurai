use chrono::{DateTime, Utc};
use serde::{Deserialize, Serialize};
use serde_json::Value;
use sqlx::FromRow;

/// ISS Fetch Log - запись о мониторинге МКС
#[derive(Debug, Clone, Serialize, Deserialize, FromRow)]
pub struct IssFetchLog {
    pub id: i64,
    pub fetched_at: DateTime<Utc>,
    pub source_url: String,
    pub payload: Value,
}

impl IssFetchLog {
    pub fn new(source_url: String, payload: Value) -> Self {
        Self {
            id: 0,
            fetched_at: Utc::now(),
            source_url,
            payload,
        }
    }
}

/// OSDR Item - данные из NASA OSDR
#[derive(Debug, Clone, Serialize, Deserialize, FromRow)]
pub struct OsdrItem {
    pub id: i64,
    pub dataset_id: Option<String>,
    pub title: Option<String>,
    pub status: Option<String>,
    pub updated_at: Option<DateTime<Utc>>,
    pub inserted_at: DateTime<Utc>,
    pub raw: Value,
}

/// Space Cache - универсальный кэш космоданных
#[derive(Debug, Clone, Serialize, Deserialize, FromRow)]
pub struct SpaceCache {
    pub id: i64,
    pub source: String,
    pub fetched_at: DateTime<Utc>,
    pub payload: Value,
}

impl SpaceCache {
    pub fn new(source: String, payload: Value) -> Self {
        Self {
            id: 0,
            source,
            fetched_at: Utc::now(),
            payload,
        }
    }
}

/// ISS Trend - тренд движения МКС
#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct IssTrend {
    pub movement: bool,
    pub delta_km: f64,
    pub dt_sec: f64,
    pub velocity_kmh: Option<f64>,
    pub from_time: Option<DateTime<Utc>>,
    pub to_time: Option<DateTime<Utc>>,
    pub from_lat: Option<f64>,
    pub from_lon: Option<f64>,
    pub to_lat: Option<f64>,
    pub to_lon: Option<f64>,
}

impl IssTrend {
    pub fn empty() -> Self {
        Self {
            movement: false,
            delta_km: 0.0,
            dt_sec: 0.0,
            velocity_kmh: None,
            from_time: None,
            to_time: None,
            from_lat: None,
            from_lon: None,
            to_lat: None,
            to_lon: None,
        }
    }
}

/// API Response обёртка для успешных ответов
#[derive(Debug, Serialize)]
pub struct ApiResponse<T: Serialize> {
    pub ok: bool,
    pub data: T,
}

impl<T: Serialize> ApiResponse<T> {
    pub fn success(data: T) -> Self {
        Self { ok: true, data }
    }
}
