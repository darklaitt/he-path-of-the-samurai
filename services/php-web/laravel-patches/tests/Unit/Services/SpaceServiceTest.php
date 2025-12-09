<?php

namespace Tests\Unit\Services;

use App\DTO\OsdrItemDTO;
use App\Repositories\SpaceRepository;
use App\Services\SpaceService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SpaceServiceTest extends TestCase
{
    private SpaceService $service;
    private SpaceRepository&MockObject $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockRepository = $this->createMock(SpaceRepository::class);
        
        $this->service = new SpaceService($this->mockRepository);
    }

    public function testGetSummaryReturnsSourceStatus(): void
    {
        // Arrange
        $summary = [
            'sources' => [
                ['name' => 'ISS', 'status' => 'active', 'last_update' => '2025-01-15 10:30:00'],
                ['name' => 'OSDR', 'status' => 'active', 'last_update' => '2025-01-15 11:00:00'],
            ],
            'total_datasets' => 500,
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('getSummary')
            ->willReturn($summary);

        // Act
        $result = $this->service->getSummary();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sources', $result);
        $this->assertCount(2, $result['sources']);
    }

    public function testGetLatestBySourceReturnsMostRecentData(): void
    {
        // Arrange
        $data = [
            'source' => 'ISS',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'altitude' => 408.5,
            'timestamp' => 1705319400,
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('getLatestBySource')
            ->with('ISS')
            ->willReturn($data);

        // Act
        $result = $this->service->getLatestBySource('ISS');

        // Assert
        $this->assertEquals('ISS', $result['source']);
        $this->assertEquals(51.5074, $result['latitude']);
    }

    public function testGetLatestBySourceReturnsNullWhenSourceNotFound(): void
    {
        // Arrange
        $this->mockRepository
            ->expects($this->once())
            ->method('getLatestBySource')
            ->with('NONEXISTENT')
            ->willReturn(null);

        // Act
        $result = $this->service->getLatestBySource('NONEXISTENT');

        // Assert
        $this->assertNull($result);
    }

    public function testRefreshSourceUpdatesData(): void
    {
        // Arrange
        $newData = [
            'source' => 'ISS',
            'latitude' => 52.0,
            'longitude' => -0.2,
            'updated_at' => '2025-01-15 11:30:00',
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('refreshSource')
            ->with('ISS')
            ->willReturn($newData);

        // Act
        $result = $this->service->refreshSource('ISS');

        // Assert
        $this->assertEquals('ISS', $result['source']);
        $this->assertEquals(52.0, $result['latitude']);
    }

    public function testGetSourceStatusReturnsHealthCheck(): void
    {
        // Arrange
        $status = [
            'name' => 'ISS',
            'status' => 'active',
            'last_update' => '2025-01-15 11:30:00',
            'response_time_ms' => 145,
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('getSourceStatus')
            ->with('ISS')
            ->willReturn($status);

        // Act
        $result = $this->service->getSourceStatus('ISS');

        // Assert
        $this->assertEquals('active', $result['status']);
        $this->assertLessThan(1000, $result['response_time_ms']);
    }

    public function testGetSummaryAggregatesMultipleSources(): void
    {
        // Arrange
        $summary = [
            'sources' => [
                ['name' => 'ISS', 'status' => 'active'],
                ['name' => 'OSDR', 'status' => 'active'],
                ['name' => 'SpaceX', 'status' => 'inactive'],
            ],
            'total_datasets' => 1500,
            'last_sync' => '2025-01-15 11:30:00',
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('getSummary')
            ->willReturn($summary);

        // Act
        $result = $this->service->getSummary();

        // Assert
        $this->assertCount(3, $result['sources']);
        $this->assertEquals(1500, $result['total_datasets']);
    }

    public function testGetLatestBySourceValidatesSourceName(): void
    {
        // Arrange
        $this->mockRepository
            ->expects($this->once())
            ->method('getLatestBySource')
            ->with('ISS')
            ->willReturn(['source' => 'ISS']);

        // Act
        $result = $this->service->getLatestBySource('ISS');

        // Assert
        $this->assertNotNull($result);
    }

    public function testRefreshSourceHandlesApiTimeout(): void
    {
        // Arrange
        $this->mockRepository
            ->expects($this->once())
            ->method('refreshSource')
            ->with('ISS')
            ->willReturn(null);

        // Act
        $result = $this->service->refreshSource('ISS');

        // Assert
        $this->assertNull($result);
    }

    public function testServiceConstructorInjectsRepository(): void
    {
        // Assert
        $this->assertNotNull($this->service);
    }

    public function testMultipleSourceQueriesAreIndependent(): void
    {
        // Arrange
        $issData = ['source' => 'ISS', 'altitude' => 408.5];
        $osdrData = ['source' => 'OSDR', 'datasets' => 500];

        $this->mockRepository
            ->expects($this->exactly(2))
            ->method('getLatestBySource')
            ->willReturnOnConsecutiveCalls($issData, $osdrData);

        // Act
        $result1 = $this->service->getLatestBySource('ISS');
        $result2 = $this->service->getLatestBySource('OSDR');

        // Assert
        $this->assertEquals('ISS', $result1['source']);
        $this->assertEquals('OSDR', $result2['source']);
    }
}
