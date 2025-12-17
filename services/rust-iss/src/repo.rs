use crate::domain::*;
use crate::error::ApiError;
use chrono::{DateTime, Utc};
use serde_json::Value;
use sqlx::{PgPool, Row};

// Advisory lock IDs для разных таблиц
const ISS_LOCK_ID: i64 = 1001;
const OSDR_LOCK_ID: i64 = 1002;
const SPACE_CACHE_LOCK_ID: i64 = 1003;

/// ISS Repository - работа с данными МКС
pub struct IssRepository;

impl IssRepository {
    /// Получить последний запрос ISS
    pub async fn get_last(pool: &PgPool) -> Result<Option<IssFetchLog>, ApiError> {
        let row = sqlx::query_as::<_, IssFetchLog>(
            "SELECT id, fetched_at, source_url, payload
             FROM iss_fetch_log
             ORDER BY id DESC LIMIT 1"
        )
        .fetch_optional(pool)
        .await?;

        Ok(row)
    }

    /// Сохранить новый ISS fetch с защитой от наложения (advisory lock)
    pub async fn save(pool: &PgPool, source_url: &str, payload: Value) -> Result<IssFetchLog, ApiError> {
        // Advisory lock для защиты от одновременных записей
        sqlx::query("SELECT pg_advisory_lock($1)")
            .bind(ISS_LOCK_ID)
            .execute(pool)
            .await?;

        let result = sqlx::query_as::<_, IssFetchLog>(
            "INSERT INTO iss_fetch_log (source_url, payload, fetched_at)
             VALUES ($1, $2, $3)
             RETURNING id, fetched_at, source_url, payload"
        )
        .bind(source_url)
        .bind(&payload)
        .bind(Utc::now())
        .fetch_one(pool)
        .await;

        // Освободить lock
        let _ = sqlx::query("SELECT pg_advisory_unlock($1)")
            .bind(ISS_LOCK_ID)
            .execute(pool)
            .await;

        result.map_err(ApiError::from)
    }

    /// Получить тренд (последние 2 записи)
    pub async fn get_trend(pool: &PgPool) -> Result<Vec<IssFetchLog>, ApiError> {
        let rows = sqlx::query_as::<_, IssFetchLog>(
            "SELECT id, fetched_at, source_url, payload
             FROM iss_fetch_log
             ORDER BY id DESC LIMIT 2"
        )
        .fetch_all(pool)
        .await?;

        Ok(rows)
    }
}

/// OSDR Repository - работа с данными NASA OSDR
pub struct OsdrRepository;

impl OsdrRepository {
    /// Получить список OSDR items
    pub async fn list(pool: &PgPool, limit: i64) -> Result<Vec<OsdrItem>, ApiError> {
        let rows = sqlx::query_as::<_, OsdrItem>(
            "SELECT id, dataset_id, title, status, updated_at, inserted_at, raw
             FROM osdr_items
             ORDER BY inserted_at DESC
             LIMIT $1"
        )
        .bind(limit)
        .fetch_all(pool)
        .await?;

        Ok(rows)
    }

    /// Получить по ID датасета
    pub async fn get_by_dataset_id(pool: &PgPool, dataset_id: &str) -> Result<Option<OsdrItem>, ApiError> {
        let row = sqlx::query_as::<_, OsdrItem>(
            "SELECT id, dataset_id, title, status, updated_at, inserted_at, raw
             FROM osdr_items
             WHERE dataset_id = $1"
        )
        .bind(dataset_id)
        .fetch_optional(pool)
        .await?;

        Ok(row)
    }

    /// Upsert по dataset_id (вставить или обновить по бизнес-ключу)
    pub async fn upsert(
        pool: &PgPool,
        dataset_id: Option<&str>,
        title: Option<String>,
        status: Option<String>,
        updated_at: Option<DateTime<Utc>>,
        raw: Value,
    ) -> Result<OsdrItem, ApiError> {
        // Advisory lock для защиты от одновременных записей
        sqlx::query("SELECT pg_advisory_lock($1)")
            .bind(OSDR_LOCK_ID)
            .execute(pool)
            .await?;

        let result = if let Some(ds_id) = dataset_id {
            sqlx::query_as::<_, OsdrItem>(
                "INSERT INTO osdr_items (dataset_id, title, status, updated_at, raw)
                 VALUES ($1, $2, $3, $4, $5)
                 ON CONFLICT (dataset_id) DO UPDATE
                 SET title = EXCLUDED.title,
                     status = EXCLUDED.status,
                     updated_at = EXCLUDED.updated_at,
                     raw = EXCLUDED.raw
                 RETURNING id, dataset_id, title, status, updated_at, inserted_at, raw"
            )
            .bind(ds_id)
            .bind(&title)
            .bind(&status)
            .bind(updated_at)
            .bind(&raw)
            .fetch_one(pool)
            .await
        } else {
            sqlx::query_as::<_, OsdrItem>(
                "INSERT INTO osdr_items (dataset_id, title, status, updated_at, raw)
                 VALUES ($1, $2, $3, $4, $5)
                 RETURNING id, dataset_id, title, status, updated_at, inserted_at, raw"
            )
            .bind::<Option<String>>(None)
            .bind(&title)
            .bind(&status)
            .bind(updated_at)
            .bind(&raw)
            .fetch_one(pool)
            .await
        };

        // Освободить lock
        let _ = sqlx::query("SELECT pg_advisory_unlock($1)")
            .bind(OSDR_LOCK_ID)
            .execute(pool)
            .await;

        result.map_err(ApiError::from)
    }

    /// Получить количество items
    pub async fn count(pool: &PgPool) -> Result<i64, ApiError> {
        let row = sqlx::query("SELECT COUNT(*) as count FROM osdr_items")
            .fetch_one(pool)
            .await?;

        let count: i64 = row.get("count");
        Ok(count)
    }
}

/// Cache Repository - работа с универсальным кэшем
pub struct CacheRepository;

impl CacheRepository {
    /// Получить последний кэш по источнику
    pub async fn get_latest(pool: &PgPool, source: &str) -> Result<Option<SpaceCache>, ApiError> {
        let row = sqlx::query_as::<_, SpaceCache>(
            "SELECT id, source, fetched_at, payload
             FROM space_cache
             WHERE source = $1
             ORDER BY id DESC LIMIT 1"
        )
        .bind(source)
        .fetch_optional(pool)
        .await?;

        Ok(row)
    }

    /// Сохранить в кэш
    pub async fn save(pool: &PgPool, source: &str, payload: Value) -> Result<SpaceCache, ApiError> {
        // Advisory lock для защиты от одновременных записей
        sqlx::query("SELECT pg_advisory_lock($1)")
            .bind(SPACE_CACHE_LOCK_ID)
            .execute(pool)
            .await?;

        let result = sqlx::query_as::<_, SpaceCache>(
            "INSERT INTO space_cache (source, payload, fetched_at)
             VALUES ($1, $2, $3)
             RETURNING id, source, fetched_at, payload"
        )
        .bind(source)
        .bind(&payload)
        .bind(Utc::now())
        .fetch_one(pool)
        .await;

        // Освободить lock
        let _ = sqlx::query("SELECT pg_advisory_unlock($1)")
            .bind(SPACE_CACHE_LOCK_ID)
            .execute(pool)
            .await;

        result.map_err(ApiError::from)
    }

    /// Получить все источники (для summary)
    pub async fn get_all_sources(pool: &PgPool) -> Result<Vec<SpaceCache>, ApiError> {
        let rows = sqlx::query_as::<_, SpaceCache>(
            "SELECT DISTINCT ON (source) id, source, fetched_at, payload
             FROM space_cache
             ORDER BY source, id DESC"
        )
        .fetch_all(pool)
        .await?;

        Ok(rows)
    }
}
