<?php

namespace Tests\Helpers;

use App\DTO\IssDTO;
use App\DTO\IssTrendDTO;
use App\DTO\OsdrItemDTO;

/**
 * Test Data Factory
 * Provides helper methods to create test objects
 */
class TestDataFactory
{
    /**
     * Create a sample IssDTO for testing
     */
    public static function createIssDTO(array $overrides = []): IssDTO
    {
        $defaults = [
            'id' => 1,
            'fetched_at' => new \DateTimeImmutable('2025-01-15 10:30:00'),
            'source_url' => 'https://api.wheretheiss.at/v1/satellites/25544',
            'payload' => [
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'altitude' => 408.5,
                'velocity' => 7.66,
            ],
        ];

        $data = array_merge($defaults, $overrides);

        return new IssDTO(
            id: $data['id'],
            fetched_at: $data['fetched_at'],
            source_url: $data['source_url'],
            payload: $data['payload'],
        );
    }

    /**
     * Create a sample IssTrendDTO for testing
     */
    public static function createIssTrendDTO(array $overrides = []): IssTrendDTO
    {
        $defaults = [
            'movement' => [
                ['lat' => 51.5, 'lon' => -0.1, 'ts' => 1705319400],
                ['lat' => 51.51, 'lon' => -0.11, 'ts' => 1705319500],
            ],
            'delta_km' => 12.5,
            'dt_sec' => 100,
            'velocity_kmh' => 27.6,
            'from_time' => new \DateTimeImmutable('2025-01-15 10:30:00'),
            'to_time' => new \DateTimeImmutable('2025-01-15 10:31:40'),
        ];

        $data = array_merge($defaults, $overrides);

        return new IssTrendDTO(
            movement: $data['movement'],
            delta_km: $data['delta_km'],
            dt_sec: $data['dt_sec'],
            velocity_kmh: $data['velocity_kmh'],
            from_time: $data['from_time'],
            to_time: $data['to_time'],
        );
    }

    /**
     * Create a sample OsdrItemDTO for testing
     */
    public static function createOsdrItemDTO(array $overrides = []): OsdrItemDTO
    {
        $defaults = [
            'id' => 1,
            'dataset_id' => 'dataset_001',
            'dataset_title' => 'Space Mission Data',
            'variables' => ['temperature', 'pressure', 'velocity'],
            'source' => 'NASA OSDR',
            'created_at' => new \DateTimeImmutable('2025-01-15 10:00:00'),
        ];

        $data = array_merge($defaults, $overrides);

        return new OsdrItemDTO(
            id: $data['id'],
            dataset_id: $data['dataset_id'],
            dataset_title: $data['dataset_title'],
            variables: $data['variables'],
            source: $data['source'],
            created_at: $data['created_at'],
        );
    }

    /**
     * Create multiple OsdrItemDTO instances
     */
    public static function createOsdrItemDTOCollection(int $count = 10): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = self::createOsdrItemDTO([
                'id' => $i + 1,
                'dataset_id' => "dataset_" . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'dataset_title' => "Dataset " . ($i + 1),
            ]);
        }
        return $items;
    }

    /**
     * Create a sample HTTP response for ISS data
     */
    public static function createIssApiResponse(): array
    {
        return [
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'altitude' => 408.5,
            'velocity' => 7.66,
            'timestamp' => 1705319400,
        ];
    }

    /**
     * Create a sample HTTP response for OSDR list
     */
    public static function createOsdrApiResponse(int $count = 10): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'dataset_id' => "dataset_" . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'dataset_title' => "Dataset " . ($i + 1),
                'variables' => ['var1', 'var2', 'var3'],
                'source' => 'NASA',
            ];
        }
        return $items;
    }

    /**
     * Create mock API response data for testing retry logic
     */
    public static function createTimeoutResponse(): \Exception
    {
        return new \Exception('Connection timeout: Could not connect to API endpoint');
    }

    /**
     * Create a sample pagination response
     */
    public static function createPaginatedResponse(
        int $total = 1000,
        int $page = 1,
        int $perPage = 100
    ): array {
        return [
            'data' => self::createOsdrApiResponse($perPage),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ];
    }
}
