use crate::error::ApiError;
use redis::{Client, AsyncCommands};

/// Redis Client для кэширования
#[derive(Clone)]
pub struct CacheClient {
    client: Client,
    default_ttl: usize,
}

impl CacheClient {
    pub async fn new(redis_url: &str, default_ttl_secs: usize) -> Result<Self, ApiError> {
        let client = redis::Client::open(redis_url)
            .map_err(|e| ApiError::internal_error(format!("Failed to create Redis client: {}", e)))?;


        let mut conn = client.get_multiplexed_async_connection()
            .await
            .map_err(|e| ApiError::internal_error(format!("Failed to connect to Redis: {}", e)))?;
        redis::cmd("PING").query_async::<_, String>(&mut conn)
            .await
            .map_err(|e| ApiError::internal_error(format!("Redis PING failed: {}", e)))?;

        Ok(Self {
            client,
            default_ttl: default_ttl_secs,
        })
    }

    /// Получить значение из кэша
    pub async fn get<T: serde::de::DeserializeOwned>(&self, key: &str) -> Result<Option<T>, ApiError> {
        let mut conn = self.client.get_multiplexed_async_connection()
            .await
            .map_err(|e| {
                tracing::warn!("Redis connection error: {}", e);
                ApiError::internal_error(format!("Cache connection error: {}", e))
            })?;
        match conn.get::<&str, Option<String>>(key).await {
            Ok(Some(data)) => {
                serde_json::from_str(&data)
                    .map_err(|e| ApiError::internal_error(format!("Failed to deserialize cache: {}", e)))
                    .map(Some)
            }
            Ok(None) => Ok(None),
            Err(e) => {
                tracing::warn!("Cache GET error for {}: {}", key, e);
                Ok(None) // Graceful degradation
            }
        }
    }

    /// Сохранить значение в кэш
    pub async fn set<T: serde::Serialize>(
        &self,
        key: &str,
        value: &T,
        ttl_secs: Option<usize>,
    ) -> Result<(), ApiError> {
        let mut conn = self.client.get_multiplexed_async_connection()
            .await
            .map_err(|e| {
                tracing::warn!("Redis connection error: {}", e);
                ApiError::internal_error(format!("Cache connection error: {}", e))
            })?;
        let ttl = ttl_secs.unwrap_or(self.default_ttl) as u64;
        let data = serde_json::to_string(value)
            .map_err(|e| ApiError::internal_error(format!("Failed to serialize cache: {}", e)))?;

        conn.set_ex::<_, _, ()>(key, data, ttl)
            .await
            .map_err(|e| {
                tracing::warn!("Cache SET error for {}: {}", key, e);
                ApiError::internal_error(format!("Cache error: {}", e))
            })?
        ;
        Ok(())
    }

    /// Удалить значение из кэша
    pub async fn delete(&self, key: &str) -> Result<(), ApiError> {
        let mut conn = self.client.get_multiplexed_async_connection()
            .await
            .map_err(|e| {
                tracing::warn!("Redis connection error: {}", e);
                ApiError::internal_error(format!("Cache connection error: {}", e))
            })?;
        conn.del::<_, ()>(key).await.map_err(|e| {
            tracing::warn!("Cache DELETE error for {}: {}", key, e);
            ApiError::internal_error(format!("Cache error: {}", e))
        })?;
        Ok(())
    }

    /// Инвалидировать ключ по префиксу
    pub async fn invalidate_prefix(&self, prefix: &str) -> Result<usize, ApiError> {
        let mut conn = self.client.get_multiplexed_async_connection()
            .await
            .map_err(|e| {
                tracing::warn!("Redis connection error: {}", e);
                ApiError::internal_error(format!("Cache connection error: {}", e))
            })?;
        let keys: Vec<String> = conn
            .keys(format!("{}*", prefix))
            .await
            .map_err(|e| {
                tracing::warn!("Cache KEYS error for prefix {}: {}", prefix, e);
                ApiError::internal_error(format!("Cache error: {}", e))
            })?;

        let count = keys.len();
        if count > 0 {
            conn.del::<_, ()>(keys)
                .await
                .map_err(|e| {
                    tracing::warn!("Cache multi-DELETE error: {}", e);
                    ApiError::internal_error(format!("Cache error: {}", e))
                })?;
        }

        Ok(count)
    }

    /// Получить статус Redis
    pub async fn ping(&self) -> Result<bool, ApiError> {
        let mut conn = self.client.get_multiplexed_async_connection()
            .await
            .map_err(|e| {
                tracing::warn!("Redis connection error: {}", e);
                ApiError::internal_error(format!("Cache connection error: {}", e))
            })?;
        match redis::cmd("PING")
            .query_async::<_, String>(&mut conn)
            .await
        {
            Ok(response) => Ok(response.eq_ignore_ascii_case("pong")),
            Err(e) => {
                tracing::error!("Redis PING failed: {}", e);
                Err(ApiError::internal_error("Redis connection failed"))
            }
        }
    }
}

/// Ключи кэша
pub mod cache_keys {
    pub fn iss_latest() -> &'static str {
        "iss:latest"
    }

    pub fn iss_trend() -> &'static str {
        "iss:trend"
    }

    pub fn osdr_list(limit: i64) -> String {
        format!("osdr:list:{}", limit)
    }

    pub fn osdr_count() -> &'static str {
        "osdr:count"
    }

    pub fn space_latest(source: &str) -> String {
        format!("space:{}:latest", source)
    }

    pub fn space_summary() -> &'static str {
        "space:summary"
    }

    pub fn osdr_prefix() -> &'static str {
        "osdr:"
    }

    pub fn space_prefix() -> &'static str {
        "space:"
    }
}
