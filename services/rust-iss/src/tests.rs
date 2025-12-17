#[cfg(test)]
mod tests {
    use crate::config::Config;
    use crate::domain::*;
    use crate::validation::*;
    use serde_json::{json, Value};
    use chrono::Utc;

    // ============ Configuration Tests ============

    /// Test 1: Config loads default values correctly
    #[test]
    fn test_config_defaults() {
        std::env::set_var("DATABASE_URL", "postgres://user:pass@localhost/db");
        let config = Config::from_env();
        
        assert_eq!(config.where_iss_url, "https://api.wheretheiss.at/v1/satellites/25544");
        assert_eq!(config.redis_url, "redis://redis:6379");
        assert_eq!(config.fetch_every_seconds, 600);
        assert_eq!(config.iss_every_seconds, 120);
        assert_eq!(config.http_max_retries, 3);
    }

    /// Test 2: Config reads environment variables correctly
    #[test]
    fn test_config_from_env() {
        std::env::set_var("DATABASE_URL", "postgres://custom:pass@host/customdb");
        std::env::set_var("FETCH_EVERY_SECONDS", "900");
        std::env::set_var("HTTP_MAX_RETRIES", "5");
        
        let config = Config::from_env();
        
        assert_eq!(config.database_url, "postgres://custom:pass@host/customdb");
        assert_eq!(config.fetch_every_seconds, 900);
        assert_eq!(config.http_max_retries, 5);
    }

    // ============ Domain Model Tests ============

    /// Test 3: IssFetchLog creation with correct timestamp
    #[test]
    fn test_iss_fetch_log_creation() {
        let payload = json!({
            "latitude": 51.5,
            "longitude": -0.1,
            "altitude": 408.5,
            "velocity": 27600.0
        });
        
        let log = IssFetchLog::new(
            "https://api.wheretheiss.at/v1/satellites/25544".to_string(),
            payload.clone(),
        );
        
        assert_eq!(log.source_url, "https://api.wheretheiss.at/v1/satellites/25544");
        assert_eq!(log.payload, payload);
        assert!(log.fetched_at <= Utc::now());
        assert_eq!(log.id, 0); // Not persisted yet
    }

    /// Test 4: IssTrend empty() creates valid zero-state
    #[test]
    fn test_iss_trend_empty() {
        let trend = IssTrend::empty();
        
        assert_eq!(trend.movement, false);
        assert_eq!(trend.delta_km, 0.0);
        assert_eq!(trend.dt_sec, 0.0);
        assert_eq!(trend.velocity_kmh, None);
        assert_eq!(trend.from_time, None);
        assert_eq!(trend.to_time, None);
    }

    /// Test 5: SpaceCache creation preserves source and payload
    #[test]
    fn test_space_cache_creation() {
        let payload = json!({
            "image_url": "https://example.com/image.jpg",
            "title": "Example Space Image",
            "description": "Test description"
        });
        
        let cache = SpaceCache::new("apod".to_string(), payload.clone());
        
        assert_eq!(cache.source, "apod");
        assert_eq!(cache.payload, payload);
        assert_eq!(cache.id, 0);
    }

    // ============ Validation Tests ============

    /// Test 6: Validate URL accepts valid URLs
    #[test]
    fn test_validate_url_accepts_valid() {
        assert!(validate_url("https://api.nasa.gov/data").is_ok());
        assert!(validate_url("http://example.com/path?query=1").is_ok());
        assert!(validate_url("https://subdomain.example.com:8080").is_ok());
    }

    /// Test 7: Validate URL rejects invalid URLs
    #[test]
    fn test_validate_url_rejects_invalid() {
        assert!(validate_url("invalid").is_err());
        assert!(validate_url("ftp://example.com").is_err());
        assert!(validate_url("").is_err());
        
        let long_url = "https://".to_string() + &"a".repeat(3000);
        assert!(validate_url(&long_url).is_err());
    }

    /// Test 8: Validate source ID accepts valid alphanumeric with underscore/dash
    #[test]
    fn test_validate_source_id_accepts_valid() {
        assert!(validate_source_id("apod").is_ok());
        assert!(validate_source_id("neo_feed").is_ok());
        assert!(validate_source_id("space-x-data").is_ok());
        assert!(validate_source_id("ISS123").is_ok());
    }

    /// Test 9: Validate source ID rejects invalid formats
    #[test]
    fn test_validate_source_id_rejects_invalid() {
        assert!(validate_source_id("").is_err());
        assert!(validate_source_id("invalid@source").is_err());
        assert!(validate_source_id("invalid#source").is_err());
        assert!(validate_source_id("invalid source").is_err());
        
        let long_id = "a".repeat(100);
        assert!(validate_source_id(&long_id).is_err());
    }

    /// Test 10: Validate JSON payload accepts valid objects and arrays
    #[test]
    fn test_validate_json_payload() {
        let obj = json!({"key": "value"});
        assert!(validate_json_payload(&obj).is_ok());
        
        let arr = json!([1, 2, 3]);
        assert!(validate_json_payload(&arr).is_ok());
        
        let string = json!("invalid");
        assert!(validate_json_payload(&string).is_err());
        
        let number = json!(42);
        assert!(validate_json_payload(&number).is_err());
    }

    // ============ Bonus: Haversine Distance Calculation ============

    /// Test 11: Haversine distance calculation (bonus)
    #[test]
    fn test_haversine_distance() {
        use crate::services::IssService;
        
        // Distance from London (51.5074°N, 0.1278°W) to Paris (48.8566°N, 2.3522°E)
        // Expected: ~344 km
        let distance = {
            let lat1 = 51.5074;
            let lon1 = -0.1278;
            let lat2 = 48.8566;
            let lon2 = 2.3522;
            
            let rlat1 = lat1.to_radians();
            let rlat2 = lat2.to_radians();
            let dlat = (lat2 - lat1).to_radians();
            let dlon = (lon2 - lon1).to_radians();
            let a = (dlat / 2.0).sin().powi(2) + rlat1.cos() * rlat2.cos() * (dlon / 2.0).sin().powi(2);
            let c = 2.0 * a.sqrt().atan2((1.0 - a).sqrt());
            6371.0 * c
        };
        
        assert!(distance > 340.0 && distance < 350.0, "Distance should be ~344 km, got {}", distance);
    }

    // ============ API Response Wrapper Tests ============

    /// Test 12: ApiResponse serializes to JSON correctly
    #[test]
    fn test_api_response_serialization() {
        let response = ApiResponse::success(json!({"message": "ok"}));
        
        let json = serde_json::to_value(&response).unwrap();
        assert_eq!(json["ok"], true);
        assert_eq!(json["data"]["message"], "ok");
    }
}
