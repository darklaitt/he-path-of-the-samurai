use crate::clients::ApiClient;
use crate::domain::*;
use crate::error::ApiError;
use crate::repo::*;
use chrono::Utc;
use serde_json::Value;
use sqlx::PgPool;
use tracing::{error, info};

/// ISS Service - бизнес-логика для МКС
#[derive(Clone)]
pub struct IssService {
    pool: PgPool,
    client: ApiClient,
}

impl IssService {
    pub fn new(pool: PgPool, client: ApiClient) -> Self {
        Self { pool, client }
    }

    /// Получить последние данные МКС
    pub async fn get_last(&self) -> Result<Option<IssFetchLog>, ApiError> {
        IssRepository::get_last(&self.pool).await
    }

    /// Получить тренд движения МКС
    pub async fn get_trend(&self) -> Result<IssTrend, ApiError> {
        let rows = IssRepository::get_trend(&self.pool).await?;

        if rows.len() < 2 {
            return Ok(IssTrend::empty());
        }

        let to = &rows[0];
        let from = &rows[1];

        let from_lat = Self::extract_f64(&from.payload, "latitude");
        let from_lon = Self::extract_f64(&from.payload, "longitude");
        let to_lat = Self::extract_f64(&to.payload, "latitude");
        let to_lon = Self::extract_f64(&to.payload, "longitude");
        let velocity = Self::extract_f64(&to.payload, "velocity");

        let mut delta_km = 0.0;
        let mut movement = false;

        if let (Some(lat1), Some(lon1), Some(lat2), Some(lon2)) = (from_lat, from_lon, to_lat, to_lon) {
            delta_km = Self::haversine_km(lat1, lon1, lat2, lon2);
            movement = delta_km > 0.1;
        }

        let dt_sec = (to.fetched_at - from.fetched_at).num_milliseconds() as f64 / 1000.0;

        Ok(IssTrend {
            movement,
            delta_km,
            dt_sec,
            velocity_kmh: velocity,
            from_time: Some(from.fetched_at),
            to_time: Some(to.fetched_at),
            from_lat,
            from_lon,
            to_lat,
            to_lon,
        })
    }

    /// Fetch ISS данные и сохранить
    pub async fn fetch_and_save(&self) -> Result<IssFetchLog, ApiError> {
        let payload = self.client.fetch_iss().await?;
        let source_url = self.client.config().where_iss_url.clone();

        IssRepository::save(&self.pool, &source_url, payload).await
    }

    fn extract_f64(value: &Value, key: &str) -> Option<f64> {
        if let Some(v) = value.get(key) {
            if let Some(f) = v.as_f64() {
                return Some(f);
            }
            if let Some(s) = v.as_str() {
                return s.parse::<f64>().ok();
            }
        }
        None
    }

    fn haversine_km(lat1: f64, lon1: f64, lat2: f64, lon2: f64) -> f64 {
        let rlat1 = lat1.to_radians();
        let rlat2 = lat2.to_radians();
        let dlat = (lat2 - lat1).to_radians();
        let dlon = (lon2 - lon1).to_radians();
        let a = (dlat / 2.0).sin().powi(2) + rlat1.cos() * rlat2.cos() * (dlon / 2.0).sin().powi(2);
        let c = 2.0 * a.sqrt().atan2((1.0 - a).sqrt());
        6371.0 * c
    }
}

/// OSDR Service - бизнес-логика для NASA OSDR
#[derive(Clone)]
pub struct OsdrService {
    pool: PgPool,
    client: ApiClient,
}

impl OsdrService {
    pub fn new(pool: PgPool, client: ApiClient) -> Self {
        Self { pool, client }
    }

    /// Получить список items
    pub async fn list(&self, limit: Option<i64>) -> Result<Vec<OsdrItem>, ApiError> {
        let limit = limit.unwrap_or(20).min(100).max(1);
        OsdrRepository::list(&self.pool, limit).await
    }

    /// Синхронизировать с внешним API
    pub async fn sync(&self) -> Result<usize, ApiError> {
        let json = self.client.fetch_osdr().await?;
        let items = self.parse_items_array(json);

        let mut written = 0usize;
        for item in items {
            if let Err(e) = self.save_item(item).await {
                error!("Failed to save OSDR item: {}", e);
                continue;
            }
            written += 1;
        }

        info!("OSDR sync completed: {} items written", written);
        Ok(written)
    }

    async fn save_item(&self, item: Value) -> Result<(), ApiError> {
        let id = Self::pick_string(&item, &["dataset_id", "id", "uuid", "studyId"]);
        let title = Self::pick_string(&item, &["title", "name", "label"]);
        let status = Self::pick_string(&item, &["status", "state", "lifecycle"]);
        let updated = Self::pick_datetime(&item, &["updated", "updated_at", "modified"]);

        OsdrRepository::upsert(&self.pool, id.as_deref(), title, status, updated, item).await?;
        Ok(())
    }

    fn parse_items_array(&self, json: Value) -> Vec<Value> {
        if let Some(a) = json.as_array() {
            a.clone()
        } else if let Some(v) = json.get("items").and_then(|x| x.as_array()) {
            v.clone()
        } else if let Some(v) = json.get("results").and_then(|x| x.as_array()) {
            v.clone()
        } else {
            vec![json]
        }
    }

    fn pick_string(value: &Value, keys: &[&str]) -> Option<String> {
        for k in keys {
            if let Some(v) = value.get(k) {
                if let Some(s) = v.as_str() {
                    if !s.is_empty() {
                        return Some(s.to_string());
                    }
                } else if v.is_number() {
                    return Some(v.to_string());
                }
            }
        }
        None
    }

    fn pick_datetime(value: &Value, keys: &[&str]) -> Option<chrono::DateTime<Utc>> {
        for k in keys {
            if let Some(v) = value.get(k) {
                if let Some(s) = v.as_str() {
                    if let Ok(dt) = s.parse::<chrono::DateTime<Utc>>() {
                        return Some(dt);
                    }
                }
            }
        }
        None
    }
}

/// Space Service - бизнес-логика для универсального кэша
#[derive(Clone)]
pub struct SpaceService {
    pool: PgPool,
    client: ApiClient,
}

impl SpaceService {
    pub fn new(pool: PgPool, client: ApiClient) -> Self {
        Self { pool, client }
    }

    /// Получить последний кэш по источнику
    pub async fn get_latest(&self, source: &str) -> Result<Option<SpaceCache>, ApiError> {
        CacheRepository::get_latest(&self.pool, source).await
    }

    /// Обновить кэш для конкретного источника
    pub async fn refresh_source(&self, source: &str) -> Result<SpaceCache, ApiError> {
        let payload = match source {
            "apod" => self.client.fetch_apod().await?,
            "neo" => {
                let (start, end) = Self::date_range(2);
                self.client.fetch_neo(&start, &end).await?
            }
            "flr" => {
                let (start, end) = Self::date_range(5);
                self.client.fetch_donki_flr(&start, &end).await?
            }
            "cme" => {
                let (start, end) = Self::date_range(5);
                self.client.fetch_donki_cme(&start, &end).await?
            }
            "spacex" => self.client.fetch_spacex_next().await?,
            _ => return Err(ApiError::bad_request("INVALID_SOURCE", "Unknown source")),
        };

        CacheRepository::save(&self.pool, source, payload).await
    }

    /// Получить summary всех источников
    pub async fn summary(&self) -> Result<Value, ApiError> {
        let caches = CacheRepository::get_all_sources(&self.pool).await?;
        let osdr_count = OsdrRepository::count(&self.pool).await?;
        let iss_last = IssRepository::get_last(&self.pool).await?;

        let mut data = serde_json::json!({
            "osdr_count": osdr_count,
            "sources": {}
        });

        for cache in caches {
            data["sources"][&cache.source] = serde_json::json!({
                "at": cache.fetched_at,
                "payload": cache.payload
            });
        }

        if let Some(iss) = iss_last {
            data["iss"] = serde_json::json!({
                "at": iss.fetched_at,
                "payload": iss.payload
            });
        }

        Ok(data)
    }

    fn date_range(days_back: i64) -> (String, String) {
        let to = Utc::now().date_naive();
        let from = to - chrono::Duration::days(days_back);
        (from.to_string(), to.to_string())
    }
}
