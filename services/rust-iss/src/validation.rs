use validator::Validate;
use serde_json::Value;

/// Валидация параметров запроса ISS
#[derive(Debug, Validate)]
pub struct IssQueryParams {
    #[validate(length(min = 1, max = 500))]
    pub source: Option<String>,
}

/// Валидация параметров запроса OSDR
#[derive(Debug, Validate)]
pub struct OsdrQueryParams {
    #[validate(range(min = 1, max = 100))]
    pub limit: Option<i64>,
    
    #[validate(length(min = 1, max = 100))]
    pub status: Option<String>,
}

/// Валидация параметров Space Cache
#[derive(Debug, Validate)]
pub struct SpaceCacheQueryParams {
    #[validate(length(min = 1, max = 50))]
    pub src: Option<String>,
}

/// Базовая валидация JSON payload'а
pub fn validate_json_payload(payload: &Value) -> Result<(), String> {
    if !payload.is_object() && !payload.is_array() {
        return Err("Payload должен быть объектом или массивом".to_string());
    }
    Ok(())
}

/// Валидация URL
pub fn validate_url(url: &str) -> Result<(), String> {
    if url.is_empty() {
        return Err("URL не может быть пусто".to_string());
    }
    if !url.starts_with("http://") && !url.starts_with("https://") {
        return Err("URL должен начинаться с http:// или https://".to_string());
    }
    if url.len() > 2048 {
        return Err("URL слишком длинный".to_string());
    }
    Ok(())
}

/// Валидация ID источника
pub fn validate_source_id(source: &str) -> Result<(), String> {
    if source.is_empty() || source.len() > 50 {
        return Err("Некорректный ID источника".to_string());
    }
    if !source.chars().all(|c| c.is_alphanumeric() || c == '_' || c == '-') {
        return Err("ID источника может содержать только буквы, цифры, _, -".to_string());
    }
    Ok(())
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_validate_url() {
        assert!(validate_url("https://api.nasa.gov/data").is_ok());
        assert!(validate_url("http://example.com").is_ok());
        assert!(validate_url("invalid").is_err());
        assert!(validate_url("ftp://example.com").is_err());
    }

    #[test]
    fn test_validate_source_id() {
        assert!(validate_source_id("apod").is_ok());
        assert!(validate_source_id("neo_feed").is_ok());
        assert!(validate_source_id("").is_err());
        assert!(validate_source_id("invalid@source").is_err());
    }
}
