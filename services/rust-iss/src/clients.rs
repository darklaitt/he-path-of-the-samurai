use crate::config::Config;
use crate::error::ApiError;
use reqwest::Client;
use serde_json::Value;
use std::time::Duration;

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

    pub fn config(&self) -> &Config {
        &self.config
    }

    /// Получить данные с ISS API
    pub async fn fetch_iss(&self) -> Result<Value, ApiError> {
        let resp = self.client
            .get(&self.config.where_iss_url)
            .send()
            .await?;

        if !resp.status().is_success() {
            return Err(ApiError::upstream_error(format!(
                "ISS API returned {}",
                resp.status()
            )));
        }

        resp.json().await.map_err(ApiError::from)
    }

    /// Получить данные из NASA OSDR
    pub async fn fetch_osdr(&self) -> Result<Value, ApiError> {
        let mut req = self.client.get(&self.config.nasa_api_url);

        if !self.config.nasa_api_key.is_empty() {
            req = req.query(&[("api_key", &self.config.nasa_api_key)]);
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

    /// Получить APOD (Astronomy Picture of the Day)
    pub async fn fetch_apod(&self) -> Result<Value, ApiError> {
        let url = "https://api.nasa.gov/planetary/apod";
        let mut req = self.client.get(url).query(&[("thumbs", "true")]);

        if !self.config.nasa_api_key.is_empty() {
            req = req.query(&[("api_key", &self.config.nasa_api_key)]);
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

    /// Получить NEO (Near Earth Objects)
    pub async fn fetch_neo(&self, start_date: &str, end_date: &str) -> Result<Value, ApiError> {
        let url = "https://api.nasa.gov/neo/rest/v1/feed";
        let mut req = self.client.get(url)
            .query(&[("start_date", start_date), ("end_date", end_date)]);

        if !self.config.nasa_api_key.is_empty() {
            req = req.query(&[("api_key", &self.config.nasa_api_key)]);
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

    /// Получить DONKI Flare Events
    pub async fn fetch_donki_flr(&self, start_date: &str, end_date: &str) -> Result<Value, ApiError> {
        let url = "https://api.nasa.gov/DONKI/FLR";
        let mut req = self.client.get(url)
            .query(&[("startDate", start_date), ("endDate", end_date)]);

        if !self.config.nasa_api_key.is_empty() {
            req = req.query(&[("api_key", &self.config.nasa_api_key)]);
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

    /// Получить DONKI CME Events
    pub async fn fetch_donki_cme(&self, start_date: &str, end_date: &str) -> Result<Value, ApiError> {
        let url = "https://api.nasa.gov/DONKI/CME";
        let mut req = self.client.get(url)
            .query(&[("startDate", start_date), ("endDate", end_date)]);

        if !self.config.nasa_api_key.is_empty() {
            req = req.query(&[("api_key", &self.config.nasa_api_key)]);
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

    /// Получить SpaceX Next Launch
    pub async fn fetch_spacex_next(&self) -> Result<Value, ApiError> {
        let url = "https://api.spacexdata.com/v4/launches/next";
        let resp = self.client.get(url).send().await?;

        if !resp.status().is_success() {
            return Err(ApiError::upstream_error(format!(
                "SpaceX API returned {}",
                resp.status()
            )));
        }

        resp.json().await.map_err(ApiError::from)
    }
}
