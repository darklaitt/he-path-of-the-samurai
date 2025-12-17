use crate::clients::ApiClient;
use crate::domain::*;
use crate::error::ApiError;
use crate::repo::*;
use crate::cache::{CacheClient, cache_keys};
use chrono::Utc;
use serde_json::Value;
use sqlx::PgPool;
use tracing::{error, info};

/// ISS Service - бизнес-логика для МКС
#[derive(Clone)]
pub struct IssService {
    pool: PgPool,
    client: ApiClient,
    cache: CacheClient,
}

impl IssService {
    pub fn new(pool: PgPool, client: ApiClient, cache: CacheClient) -> Self {
        Self { pool, client, cache }
    }

    /// Получить последние данные МКС
    pub async fn get_last(&self) -> Result<Option<IssFetchLog>, ApiError> {
        // Проверяем кэш
        if let Ok(Some(cached)) = self.cache.get::<IssFetchLog>(cache_keys::iss_latest()).await {
            return Ok(Some(cached));
        }
        
        // Если нет в кэше, получаем из БД
        let result = IssRepository::get_last(&self.pool).await?;
        
        // Сохраняем в кэш
        if let Some(ref log) = result {
            let _ = self.cache.set(cache_keys::iss_latest(), log, Some(300)).await;
        }
        
        Ok(result)
    }

    /// Получить тренд движения МКС
    pub async fn get_trend(&self) -> Result<IssTrend, ApiError> {
        // Проверяем кэш
        if let Ok(Some(cached)) = self.cache.get::<IssTrend>(cache_keys::iss_trend()).await {
            return Ok(cached);
        }
        
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

        let trend = IssTrend {
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
        };
        
        // Сохраняем в кэш
        let _ = self.cache.set(cache_keys::iss_trend(), &trend, Some(60)).await;
        
        Ok(trend)
    }

    /// Fetch ISS данные и сохранить
    pub async fn fetch_and_save(&self) -> Result<IssFetchLog, ApiError> {
        let payload = self.client.fetch_iss().await?;
        let source_url = self.client.config().where_iss_url.clone();

        let log = IssRepository::save(&self.pool, &source_url, payload).await?;
        
        // Инвалидируем кэш
        let _ = self.cache.delete(cache_keys::iss_latest()).await;
        let _ = self.cache.delete(cache_keys::iss_trend()).await;
        
        Ok(log)
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
    cache: CacheClient,
}

impl OsdrService {
    pub fn new(pool: PgPool, client: ApiClient, cache: CacheClient) -> Self {
        Self { pool, client, cache }
    }

    /// Получить список items
    pub async fn list(&self, limit: Option<i64>) -> Result<Vec<OsdrItem>, ApiError> {
        let limit = limit.unwrap_or(20).min(100).max(1);
        
        // Проверяем кэш
        let cache_key = cache_keys::osdr_list(limit);
        if let Ok(Some(cached)) = self.cache.get::<Vec<OsdrItem>>(&cache_key).await {
            return Ok(cached);
        }
        
        // Если нет в кэше, получаем из БД
        let result = OsdrRepository::list(&self.pool, limit).await?;
        
        // Сохраняем в кэш
        let _ = self.cache.set(&cache_key, &result, Some(600)).await;
        
        Ok(result)
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
        
        // Инвалидируем кэш OSDR
        let _ = self.cache.invalidate_prefix(cache_keys::osdr_prefix()).await;
        let _ = self.cache.delete(cache_keys::osdr_count()).await;
        
        Ok(written)
    }

    async fn save_item(&self, item: Value) -> Result<(), ApiError> {
        // Extract fields from OSDR Search API _source
        let id = Self::pick_string(&item, &[
            "Accession",  // OSDR Search API field
            "Study Identifier",
            "dataset_id", 
            "id", 
            "uuid", 
            "studyId"
        ]);
        
        let title = Self::pick_string(&item, &[
            "Study Title",  // OSDR Search API field
            "title", 
            "name", 
            "label"
        ]);
        
        let status = Self::pick_string(&item, &[
            "Project Type",  // OSDR Search API field
            "status", 
            "state", 
            "lifecycle"
        ]);
        
        let updated = Self::pick_datetime(&item, &[
            "Study Public Release Date",
            "updated", 
            "updated_at", 
            "modified"
        ]);

        OsdrRepository::upsert(&self.pool, id.as_deref(), title, status, updated, item).await?;
        Ok(())
    }

    fn parse_items_array(&self, json: Value) -> Vec<Value> {
        // OSDR Search API returns hits.hits[].._source structure
        // First try nested hits.hits path
        if let Some(hits_obj) = json.get("hits") {
            if let Some(hits_array) = hits_obj.get("hits").and_then(|x| x.as_array()) {
                // Extract _source from each hit
                return hits_array.iter()
                    .filter_map(|hit| hit.get("_source").cloned())
                    .collect();
            }
            // Also try direct hits array
            if let Some(hits_array) = hits_obj.as_array() {
                return hits_array.iter()
                    .filter_map(|hit| hit.get("_source").cloned())
                    .collect();
            }
        }
        
        // Fallback for other response formats
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
    cache: CacheClient,
}

impl SpaceService {
    pub fn new(pool: PgPool, client: ApiClient, cache: CacheClient) -> Self {
        Self { pool, client, cache }
    }

    /// Получить последний кэш по источнику
    pub async fn get_latest(&self, source: &str) -> Result<Option<SpaceCache>, ApiError> {
        // Проверяем Redis кэш
        let cache_key = cache_keys::space_latest(source);
        if let Ok(Some(cached)) = self.cache.get::<SpaceCache>(&cache_key).await {
            return Ok(Some(cached));
        }
        
        // Если нет в Redis, получаем из БД
        let result = CacheRepository::get_latest(&self.pool, source).await?;
        
        // Сохраняем в Redis кэш
        if let Some(ref cache) = result {
            let _ = self.cache.set(&cache_key, cache, Some(300)).await;
        }
        
        Ok(result)
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
            "jwst" => self.client.fetch_jwst().await?,
            _ => return Err(ApiError::bad_request("INVALID_SOURCE", "Unknown source")),
        };

        let cache = CacheRepository::save(&self.pool, source, payload).await?;
        
        // Инвалидируем Redis кэш для этого источника
        let _ = self.cache.delete(&cache_keys::space_latest(source)).await;
        let _ = self.cache.delete(cache_keys::space_summary()).await;
        
        Ok(cache)
    }

    /// Получить summary всех источников
    pub async fn summary(&self) -> Result<Value, ApiError> {
        // Проверяем кэш
        if let Ok(Some(cached)) = self.cache.get::<Value>(cache_keys::space_summary()).await {
            return Ok(cached);
        }
        
        let caches = CacheRepository::get_all_sources(&self.pool).await?;
        
        // Проверяем кэш для osdr_count
        let osdr_count = if let Ok(Some(cached_count)) = self.cache.get::<i64>(cache_keys::osdr_count()).await {
            cached_count
        } else {
            let count = OsdrRepository::count(&self.pool).await?;
            let _ = self.cache.set(cache_keys::osdr_count(), &count, Some(600)).await;
            count
        };
        
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
        
        // Сохраняем в кэш
        let _ = self.cache.set(cache_keys::space_summary(), &data, Some(300)).await;

        Ok(data)
    }

    fn date_range(days_back: i64) -> (String, String) {
        let to = Utc::now().date_naive();
        let from = to - chrono::Duration::days(days_back);
        (from.to_string(), to.to_string())
    }
}
