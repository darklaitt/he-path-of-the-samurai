use serde::Deserialize;

#[derive(Debug, Clone, Deserialize)]
pub struct Config {
    pub database_url: String,
    pub nasa_api_url: String,
    pub nasa_api_key: String,
    pub where_iss_url: String,
    pub redis_url: String,
    
    // Интервалы обновления (в секундах)
    pub fetch_every_seconds: u64,
    pub iss_every_seconds: u64,
    pub apod_every_seconds: u64,
    pub neo_every_seconds: u64,
    pub donki_every_seconds: u64,
    pub spacex_every_seconds: u64,
    
    // Rate limiting
    pub rate_limit_requests: u32,
    pub rate_limit_window_secs: u32,
    
    // HTTP таймауты
    pub http_timeout_secs: u64,
    pub http_connect_timeout_secs: u64,
}

impl Config {
    pub fn from_env() -> Self {
        dotenvy::dotenv().ok();
        
        Self {
            database_url: std::env::var("DATABASE_URL")
                .expect("DATABASE_URL is required"),
            nasa_api_url: std::env::var("NASA_API_URL")
                .unwrap_or_else(|_| "https://visualization.osdr.nasa.gov/biodata/api/v2/datasets/?format=json".to_string()),
            nasa_api_key: std::env::var("NASA_API_KEY").unwrap_or_default(),
            where_iss_url: std::env::var("WHERE_ISS_URL")
                .unwrap_or_else(|_| "https://api.wheretheiss.at/v1/satellites/25544".to_string()),
            redis_url: std::env::var("REDIS_URL")
                .unwrap_or_else(|_| "redis://redis:6379".to_string()),
            
            fetch_every_seconds: env_u64("FETCH_EVERY_SECONDS", 600),
            iss_every_seconds: env_u64("ISS_EVERY_SECONDS", 120),
            apod_every_seconds: env_u64("APOD_EVERY_SECONDS", 43200),
            neo_every_seconds: env_u64("NEO_EVERY_SECONDS", 7200),
            donki_every_seconds: env_u64("DONKI_EVERY_SECONDS", 3600),
            spacex_every_seconds: env_u64("SPACEX_EVERY_SECONDS", 3600),
            
            rate_limit_requests: env_u32("RATE_LIMIT_REQUESTS", 100),
            rate_limit_window_secs: env_u32("RATE_LIMIT_WINDOW_SECS", 60),
            
            http_timeout_secs: env_u64("HTTP_TIMEOUT_SECS", 30),
            http_connect_timeout_secs: env_u64("HTTP_CONNECT_TIMEOUT_SECS", 10),
        }
    }
}

fn env_u64(key: &str, default: u64) -> u64 {
    std::env::var(key)
        .ok()
        .and_then(|s| s.parse().ok())
        .unwrap_or(default)
}

fn env_u32(key: &str, default: u32) -> u32 {
    std::env::var(key)
        .ok()
        .and_then(|s| s.parse().ok())
        .unwrap_or(default)
}
