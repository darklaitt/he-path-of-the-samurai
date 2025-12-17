use axum::{
    http::StatusCode,
    response::{IntoResponse, Response},
    Json,
};
use serde_json::json;
use std::fmt;
use std::error::Error;
use uuid::Uuid;

/// Единый формат ошибок API
#[derive(Debug)]
pub struct ApiError {
    pub code: String,
    pub message: String,
    pub trace_id: String,
    pub status: StatusCode,
}

impl ApiError {
    pub fn new(code: impl Into<String>, message: impl Into<String>) -> Self {
        Self {
            code: code.into(),
            message: message.into(),
            trace_id: Uuid::new_v4().to_string(),
            status: StatusCode::INTERNAL_SERVER_ERROR,
        }
    }

    pub fn with_status(mut self, status: StatusCode) -> Self {
        self.status = status;
        self
    }

    pub fn bad_request(code: impl Into<String>, message: impl Into<String>) -> Self {
        Self::new(code, message).with_status(StatusCode::BAD_REQUEST)
    }

    pub fn not_found(message: impl Into<String>) -> Self {
        Self::new("NOT_FOUND", message).with_status(StatusCode::NOT_FOUND)
    }

    pub fn upstream_error(message: impl Into<String>) -> Self {
        Self::new("UPSTREAM_ERROR", message).with_status(StatusCode::BAD_GATEWAY)
    }

    pub fn rate_limit() -> Self {
        Self::new("RATE_LIMIT", "Too many requests").with_status(StatusCode::TOO_MANY_REQUESTS)
    }

    pub fn internal_error(message: impl Into<String>) -> Self {
        Self::new("INTERNAL_ERROR", message).with_status(StatusCode::INTERNAL_SERVER_ERROR)
    }

    pub fn database_error(message: impl Into<String>) -> Self {
        Self::new("DATABASE_ERROR", message).with_status(StatusCode::INTERNAL_SERVER_ERROR)
    }
}

impl fmt::Display for ApiError {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        write!(f, "{}: {}", self.code, self.message)
    }
}

impl Error for ApiError {}

impl IntoResponse for ApiError {
    fn into_response(self) -> Response {
        let body = json!({
            "ok": false,
            "error": {
                "code": self.code,
                "message": self.message,
                "trace_id": self.trace_id,
            }
        });

        (self.status, Json(body)).into_response()
    }
}

impl From<sqlx::Error> for ApiError {
    fn from(err: sqlx::Error) -> Self {
        tracing::error!("Database error: {:?}", err);
        ApiError::database_error("Database operation failed")
    }
}

impl From<reqwest::Error> for ApiError {
    fn from(err: reqwest::Error) -> Self {
        tracing::error!("HTTP client error: {:?}", err);
        if err.is_timeout() {
            ApiError::upstream_error("Request timeout")
        } else if err.is_connect() {
            ApiError::upstream_error("Connection failed")
        } else {
            ApiError::upstream_error("HTTP request failed")
        }
    }
}

impl From<serde_json::Error> for ApiError {
    fn from(err: serde_json::Error) -> Self {
        tracing::error!("JSON error: {:?}", err);
        ApiError::internal_error("JSON processing failed")
    }
}
