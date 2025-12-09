<?php

namespace Tests\Feature\Controllers;

use App\DTO\IssDTO;
use App\DTO\IssTrendDTO;
use App\Services\IssService;
use Illuminate\Foundation\Testing\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

abstract class FeatureTestCase extends TestCase
{
    /**
     * Create the application.
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        return $app;
    }
}

class DashboardControllerTest extends FeatureTestCase
{
    private IssService&MockObject $mockIssService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock services
        $this->mockIssService = $this->createMock(IssService::class);
        
        // Bind mocks to container
        $this->app->bind(IssService::class, fn() => $this->mockIssService);
    }

    public function testIndexPageLoads(): void
    {
        // Arrange
        $issDto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable('2025-01-15 10:30:00'),
            source_url: 'https://api.wheretheiss.at/v1/satellites/25544',
            payload: [
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'altitude' => 408.5,
                'velocity' => 7.66,
            ]
        );

        $this->mockIssService
            ->expects($this->once())
            ->method('getLastIss')
            ->willReturn($issDto);

        // Act
        $response = $this->get('/dashboard');

        // Assert
        $response->assertStatus(200);
        $response->assertViewIs('dashboard');
    }

    public function testGetLastIssApiEndpoint(): void
    {
        // Arrange
        $issDto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable(),
            source_url: 'test',
            payload: [
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'altitude' => 408.5,
            ]
        );

        $this->mockIssService
            ->expects($this->once())
            ->method('getLastIss')
            ->willReturn($issDto);

        // Act
        $response = $this->getJson('/api/iss/last');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'id',
                'fetched_at',
                'latitude',
                'longitude',
                'altitude',
            ]
        ]);
    }

    public function testGetTrendApiEndpoint(): void
    {
        // Arrange
        $trend = new IssTrendDTO(
            movement: [
                ['lat' => 51.5, 'lon' => -0.1, 'ts' => 1705319400],
                ['lat' => 51.51, 'lon' => -0.11, 'ts' => 1705319500],
            ],
            delta_km: 12.5,
            dt_sec: 100,
            velocity_kmh: 27.6,
            from_time: new \DateTimeImmutable('2025-01-15 10:30:00'),
            to_time: new \DateTimeImmutable('2025-01-15 10:31:40'),
        );

        $this->mockIssService
            ->expects($this->once())
            ->method('getTrend')
            ->willReturn($trend);

        // Act
        $response = $this->getJson('/api/iss/trend');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'delta_km',
                'velocity_kmh',
                'from_time',
                'to_time',
            ]
        ]);
    }

    public function testRefreshIssApiEndpoint(): void
    {
        // Arrange
        $newIss = new IssDTO(
            id: 2,
            fetched_at: new \DateTimeImmutable('2025-01-15 11:00:00'),
            source_url: 'test',
            payload: ['latitude' => 52.0]
        );

        $this->mockIssService
            ->expects($this->once())
            ->method('refreshLastIss')
            ->willReturn($newIss);

        // Act
        $response = $this->postJson('/api/iss/refresh');

        // Assert
        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function testRefreshIssHandlesTimeout(): void
    {
        // Arrange
        $this->mockIssService
            ->expects($this->once())
            ->method('refreshLastIss')
            ->willThrowException(new \Exception('API Timeout'));

        // Act
        $response = $this->postJson('/api/iss/refresh');

        // Assert - Should return error response
        $response->assertStatus(500);
        $response->assertJson(['ok' => false]);
    }

    public function testGetSourceStatusApiEndpoint(): void
    {
        // Act
        $response = $this->getJson('/api/iss/status');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'iss' => ['status', 'last_update'],
                'osdr' => ['status', 'last_update'],
            ]
        ]);
    }

    public function testDashboardWithCachedData(): void
    {
        // Arrange - Set cache
        $issDto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable(),
            source_url: 'test',
            payload: ['latitude' => 51.5]
        );

        // Mock service to return cached data
        $this->mockIssService
            ->method('getLastIss')
            ->willReturn($issDto);

        // Act
        $response1 = $this->get('/dashboard');
        $response2 = $this->get('/dashboard');

        // Assert - Both should succeed
        $response1->assertStatus(200);
        $response2->assertStatus(200);
    }

    public function testDashboardHandlesServiceFailure(): void
    {
        // Arrange
        $this->mockIssService
            ->expects($this->once())
            ->method('getLastIss')
            ->willReturn(null);

        // Act
        $response = $this->get('/dashboard');

        // Assert - Should show graceful degradation
        $response->assertStatus(200); // Page still loads
    }

    public function testJsonResponseHasCorrectStructure(): void
    {
        // Arrange
        $issDto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable('2025-01-15 10:30:00'),
            source_url: 'test',
            payload: ['latitude' => 51.5, 'longitude' => -0.1]
        );

        $this->mockIssService
            ->expects($this->once())
            ->method('getLastIss')
            ->willReturn($issDto);

        // Act
        $response = $this->getJson('/api/iss/last');

        // Assert
        $response->assertJsonStructure([
            'ok' => 'boolean',
            'data' => [
                'id' => 'integer',
                'fetched_at' => 'string',
                'latitude' => 'number',
                'longitude' => 'number',
            ]
        ]);
    }
}
