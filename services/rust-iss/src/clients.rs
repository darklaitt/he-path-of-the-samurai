use crate::config::Config;
use crate::error::ApiError;
use reqwest::Client;
use serde_json::Value;
use std::time::Duration;
use tokio::time::sleep;
use tracing::warn;

/// HTTP Client для работы с внешними API
#[derive(Clone)]
pub struct ApiClient {
    client: Client,
    config: Config,
}

impl ApiClient {
    pub fn new(config: Config) -> Result<Self, ApiError> {
        let timeout = Duration::from_secs(config.http_timeout_secs);
        let connect_timeout = Duration::from_secs(config.http_connect_timeout_secs);

        let client = Client::builder()
            .timeout(timeout)
            .connect_timeout(connect_timeout)
            .user_agent("rust-iss-service/0.2.0")
            .build()
            .map_err(|e| ApiError::upstream_error(format!("Failed to create HTTP client: {}", e)))?;

        Ok(Self { client, config })
    }

    /// Выполнить запрос с повторными попытками (exponential backoff)
    async fn fetch_with_retry<F, Fut>(&self, mut fetch_fn: F, max_retries: u32) -> Result<Value, ApiError>
    where
        F: FnMut() -> Fut,
        Fut: std::future::Future<Output = Result<Value, ApiError>>,
    {
        let mut retries = 0;
        loop {
            match fetch_fn().await {
                Ok(data) => return Ok(data),
                Err(e) if retries < max_retries => {
                    let backoff_secs = 2_u64.pow(retries.min(5)); // Максимум 32 секунды
                    let backoff = Duration::from_secs(backoff_secs);
                    warn!(
                        "API request failed (attempt {}/{}), retrying in {:?}: {}",
                        retries + 1,
                        max_retries + 1,
                        backoff,
                        e
                    );
                    sleep(backoff).await;
                    retries += 1;
                }
                Err(e) => return Err(e),
            }
        }
    }

    pub fn config(&self) -> &Config {
        &self.config
    }

    /// Получить данные с ISS API
    pub async fn fetch_iss(&self) -> Result<Value, ApiError> {
        let url = self.config.where_iss_url.clone();
        let client = self.client.clone();
        
        self.fetch_with_retry(
            || {
                let url = url.clone();
                let client = client.clone();
                async move {
                    let resp = client.get(&url).send().await?;
                    
                    if !resp.status().is_success() {
                        return Err(ApiError::upstream_error(format!(
                            "ISS API returned {}",
                            resp.status()
                        )));
                    }
                    
                    resp.json().await.map_err(ApiError::from)
                }
            },
            self.config.http_max_retries,
        )
        .await
    }

    /// Получить данные из NASA OSDR Search API
    pub async fn fetch_osdr(&self) -> Result<Value, ApiError> {
        let url = self.config.nasa_api_url.clone();
        let api_key = self.config.nasa_api_key.clone();
        let client = self.client.clone();
        
        self.fetch_with_retry(
            || {
                let url = url.clone();
                let api_key = api_key.clone();
                let client = client.clone();
                async move {
                    let mut req = client.get(&url)
                        .query(&[("type", "cgene")])  // Search only NASA OSDR (cgene)
                        .query(&[("from", "0")])
                        .query(&[("size", "25")]);
                    
                    if !api_key.is_empty() {
                        req = req.query(&[("api_key", &api_key)]);
                    }
                    
                    let resp = req.send().await?;
                    
                    if !resp.status().is_success() {
                        return Err(ApiError::upstream_error(format!(
                            "OSDR API returned {}",
                            resp.status()
                        )));
                    }
                    
                    resp.json().await.map_err(ApiError::from)
                }
            },
            self.config.http_max_retries,
        )
        .await
    }

    /// Получить APOD (Astronomy Picture of the Day)
    pub async fn fetch_apod(&self) -> Result<Value, ApiError> {
        let url = "https://api.nasa.gov/planetary/apod".to_string();
        let api_key = self.config.nasa_api_key.clone();
        let client = self.client.clone();
        
        self.fetch_with_retry(
            || {
                let url = url.clone();
                let api_key = api_key.clone();
                let client = client.clone();
                async move {
                    let mut req = client.get(&url).query(&[("thumbs", "true")]);
                    if !api_key.is_empty() {
                        req = req.query(&[("api_key", &api_key)]);
                    }
                    
                    let resp = req.send().await?;
                    
                    if !resp.status().is_success() {
                        return Err(ApiError::upstream_error(format!(
                            "APOD API returned {}",
                            resp.status()
                        )));
                    }
                    
                    resp.json().await.map_err(ApiError::from)
                }
            },
            self.config.http_max_retries,
        )
        .await
    }

    /// Получить NEO (Near Earth Objects)
    pub async fn fetch_neo(&self, start_date: &str, end_date: &str) -> Result<Value, ApiError> {
        let url = "https://api.nasa.gov/neo/rest/v1/feed".to_string();
        let start_date = start_date.to_string();
        let end_date = end_date.to_string();
        let api_key = self.config.nasa_api_key.clone();
        let client = self.client.clone();
        
        self.fetch_with_retry(
            || {
                let url = url.clone();
                let start_date = start_date.clone();
                let end_date = end_date.clone();
                let api_key = api_key.clone();
                let client = client.clone();
                async move {
                    let mut req = client.get(&url)
                        .query(&[("start_date", &start_date), ("end_date", &end_date)]);
                    if !api_key.is_empty() {
                        req = req.query(&[("api_key", &api_key)]);
                    }
                    
                    let resp = req.send().await?;
                    
                    if !resp.status().is_success() {
                        return Err(ApiError::upstream_error(format!(
                            "NEO API returned {}",
                            resp.status()
                        )));
                    }
                    
                    resp.json().await.map_err(ApiError::from)
                }
            },
            self.config.http_max_retries,
        )
        .await
    }

    /// Получить DONKI Flare Events
    pub async fn fetch_donki_flr(&self, start_date: &str, end_date: &str) -> Result<Value, ApiError> {
        let url = "https://api.nasa.gov/DONKI/FLR".to_string();
        let start_date = start_date.to_string();
        let end_date = end_date.to_string();
        let api_key = self.config.nasa_api_key.clone();
        let client = self.client.clone();
        
        self.fetch_with_retry(
            || {
                let url = url.clone();
                let start_date = start_date.clone();
                let end_date = end_date.clone();
                let api_key = api_key.clone();
                let client = client.clone();
                async move {
                    let mut req = client.get(&url)
                        .query(&[("startDate", &start_date), ("endDate", &end_date)]);
                    if !api_key.is_empty() {
                        req = req.query(&[("api_key", &api_key)]);
                    }
                    
                    let resp = req.send().await?;
                    
                    if !resp.status().is_success() {
                        return Err(ApiError::upstream_error(format!(
                            "DONKI FLR API returned {}",
                            resp.status()
                        )));
                    }
                    
                    resp.json().await.map_err(ApiError::from)
                }
            },
            self.config.http_max_retries,
        )
        .await
    }

    /// Получить DONKI CME Events
    pub async fn fetch_donki_cme(&self, start_date: &str, end_date: &str) -> Result<Value, ApiError> {
        let url = "https://api.nasa.gov/DONKI/CME".to_string();
        let start_date = start_date.to_string();
        let end_date = end_date.to_string();
        let api_key = self.config.nasa_api_key.clone();
        let client = self.client.clone();
        
        self.fetch_with_retry(
            || {
                let url = url.clone();
                let start_date = start_date.clone();
                let end_date = end_date.clone();
                let api_key = api_key.clone();
                let client = client.clone();
                async move {
                    let mut req = client.get(&url)
                        .query(&[("startDate", &start_date), ("endDate", &end_date)]);
                    if !api_key.is_empty() {
                        req = req.query(&[("api_key", &api_key)]);
                    }
                    
                    let resp = req.send().await?;
                    
                    if !resp.status().is_success() {
                        return Err(ApiError::upstream_error(format!(
                            "DONKI CME API returned {}",
                            resp.status()
                        )));
                    }
                    
                    resp.json().await.map_err(ApiError::from)
                }
            },
            self.config.http_max_retries,
        )
        .await
    }

    /// Получить SpaceX Next Launch
    pub async fn fetch_spacex_next(&self) -> Result<Value, ApiError> {
        let url = "https://api.spacexdata.com/v4/launches/next".to_string();
        let client = self.client.clone();
        
        self.fetch_with_retry(
            || {
                let url = url.clone();
                let client = client.clone();
                async move {
                    let resp = client.get(&url).send().await?;
                    
                    if !resp.status().is_success() {
                        return Err(ApiError::upstream_error(format!(
                            "SpaceX API returned {}",
                            resp.status()
                        )));
                    }
                    
                    resp.json().await.map_err(ApiError::from)
                }
            },
            self.config.http_max_retries,
        )
        .await
    }

    /// Получить JWST данные
    pub async fn fetch_jwst(&self) -> Result<Value, ApiError> {
        let base_url = self.config.jwst_api_url.clone();
        let api_key = self.config.jwst_api_key.clone();
        let email = self.config.jwst_email.clone();
        let program_id = self.config.jwst_program_id.clone();
        let client = self.client.clone();
        
        self.fetch_with_retry(
            || {
                let base_url = base_url.clone();
                let api_key = api_key.clone();
                let email = email.clone();
                let program_id = program_id.clone();
                let client = client.clone();
                async move {
                    // JWST API может требовать разные endpoints, используем базовый URL
                    let url = if base_url.ends_with('/') {
                        format!("{}latest", base_url)
                    } else {
                        format!("{}/latest", base_url)
                    };
                    
                    let mut req = client.get(&url);
                    
                    // Добавляем заголовки если есть API key
                    if !api_key.is_empty() {
                        req = req.header("X-API-Key", &api_key);
                    }
                    if !email.is_empty() {
                        req = req.header("X-Email", &email);
                    }
                    if !program_id.is_empty() {
                        req = req.query(&[("program_id", &program_id)]);
                    }
                    
                    let resp = req.send().await?;
                    
                    if !resp.status().is_success() {
                        return Err(ApiError::upstream_error(format!(
                            "JWST API returned {}",
                            resp.status()
                        )));
                    }
                    
                    resp.json().await.map_err(ApiError::from)
                }
            },
            self.config.http_max_retries,
        )
        .await
    }
}
