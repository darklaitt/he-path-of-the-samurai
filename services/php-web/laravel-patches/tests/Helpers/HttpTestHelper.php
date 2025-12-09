<?php

namespace Tests\Helpers;

use Illuminate\Http\Client\PendingRequest;

/**
 * HTTP Testing Helper
 * Provides utilities for mocking HTTP responses
 */
class HttpTestHelper
{
    /**
     * Create a mock successful response for ISS API
     */
    public static function mockSuccessfulIssResponse(): array
    {
        return [
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'altitude' => 408.5,
            'velocity' => 7.66,
            'visibility' => 'daylight',
            'timestamp' => 1705319400,
        ];
    }

    /**
     * Create a mock successful response for OSDR API
     */
    public static function mockSuccessfulOsdrResponse(): array
    {
        return [
            'count' => 2,
            'next' => null,
            'previous' => null,
            'results' => [
                [
                    'id' => 1,
                    'dataset_id' => 'GISS-E2-R',
                    'title' => 'GISS ModelE2-R',
                    'variables' => ['temperature', 'pressure'],
                    'source' => 'NASA',
                    'created_at' => '2025-01-15T10:00:00Z',
                ],
                [
                    'id' => 2,
                    'dataset_id' => 'CCSM4',
                    'title' => 'CCSM4 Climate Model',
                    'variables' => ['precipitation', 'wind_speed'],
                    'source' => 'NCAR',
                    'created_at' => '2025-01-15T11:00:00Z',
                ],
            ],
        ];
    }

    /**
     * Create a mock error response
     */
    public static function mockErrorResponse(int $statusCode = 500, string $message = 'Internal Server Error'): array
    {
        return [
            'status' => 'error',
            'code' => $statusCode,
            'message' => $message,
        ];
    }

    /**
     * Create a mock timeout exception message
     */
    public static function mockTimeoutException(): string
    {
        return 'cURL error 28: Operation timed out after 5000 milliseconds with 0 bytes received';
    }

    /**
     * Create a mock connection refused exception
     */
    public static function mockConnectionException(): string
    {
        return 'cURL error 7: Failed to connect to api.example.com port 443: Connection refused';
    }

    /**
     * Create a mock response with all required headers
     */
    public static function mockFullHttpResponse(): array
    {
        return [
            'status' => 200,
            'headers' => [
                'content-type' => 'application/json',
                'content-length' => '256',
                'server' => 'nginx/1.24',
                'date' => date('D, d M Y H:i:s') . ' GMT',
                'cache-control' => 'public, max-age=300',
            ],
            'body' => self::mockSuccessfulIssResponse(),
        ];
    }

    /**
     * Validate response format for ISS endpoint
     */
    public static function validateIssResponseFormat(array $response): bool
    {
        $requiredFields = ['latitude', 'longitude', 'altitude', 'velocity', 'timestamp'];
        
        foreach ($requiredFields as $field) {
            if (!isset($response[$field])) {
                return false;
            }
        }

        return is_numeric($response['latitude']) &&
               is_numeric($response['longitude']) &&
               is_numeric($response['altitude']) &&
               is_numeric($response['velocity']) &&
               is_int($response['timestamp']);
    }

    /**
     * Validate response format for OSDR endpoint
     */
    public static function validateOsdrResponseFormat(array $response): bool
    {
        if (!isset($response['results']) || !is_array($response['results'])) {
            return false;
        }

        foreach ($response['results'] as $item) {
            $requiredFields = ['dataset_id', 'title', 'variables', 'source'];
            foreach ($requiredFields as $field) {
                if (!isset($item[$field])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Create a retry-able failure scenario
     */
    public static function createRetryScenario(): array
    {
        return [
            'attempt_1' => ['status' => 'failed', 'error' => 'Network timeout'],
            'attempt_2' => ['status' => 'failed', 'error' => 'Connection reset'],
            'attempt_3' => ['status' => 'success', 'data' => self::mockSuccessfulIssResponse()],
        ];
    }

    /**
     * Get common HTTP status codes for testing
     */
    public static function getHttpStatusCodes(): array
    {
        return [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];
    }
}
