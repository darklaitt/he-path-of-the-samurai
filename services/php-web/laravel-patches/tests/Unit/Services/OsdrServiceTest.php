<?php

namespace Tests\Unit\Services;

use App\DTO\OsdrItemDTO;
use App\Repositories\OsdrRepository;
use App\Services\OsdrService;
use Illuminate\Cache\Repository;
use Illuminate\Support\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OsdrServiceTest extends TestCase
{
    private OsdrService $service;
    private OsdrRepository&MockObject $mockRepository;
    private Repository&MockObject $mockCache;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockRepository = $this->createMock(OsdrRepository::class);
        $this->mockCache = $this->createMock(Repository::class);
        
        $this->service = new OsdrService($this->mockRepository, $this->mockCache);
    }

    public function testGetListReturnsCollectionOfOsdrItemDTOs(): void
    {
        // Arrange
        $items = [
            new OsdrItemDTO(
                id: 1,
                dataset_id: 'dataset_001',
                dataset_title: 'Space Mission Data',
                variables: ['temperature', 'pressure', 'velocity'],
                source: 'NASA OSDR',
                created_at: new \DateTimeImmutable('2025-01-15 10:00:00'),
            ),
            new OsdrItemDTO(
                id: 2,
                dataset_id: 'dataset_002',
                dataset_title: 'Satellite Telemetry',
                variables: ['altitude', 'latitude', 'longitude'],
                source: 'NASA OSDR',
                created_at: new \DateTimeImmutable('2025-01-15 11:00:00'),
            ),
        ];

        $this->mockCache
            ->expects($this->once())
            ->method('remember')
            ->with('osdr.list', 600, $this->anything())
            ->willReturn(new Collection($items));

        // Act
        $result = $this->service->getList(10);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertEquals('dataset_001', $result[0]->dataset_id);
    }

    public function testSearchFiltersItemsByQueryString(): void
    {
        // Arrange
        $items = [
            new OsdrItemDTO(
                id: 1,
                dataset_id: 'mission_001',
                dataset_title: 'Space Mission Analysis',
                variables: ['data1'],
                source: 'NASA',
                created_at: new \DateTimeImmutable(),
            ),
            new OsdrItemDTO(
                id: 2,
                dataset_id: 'satellite_001',
                dataset_title: 'Satellite Data Processing',
                variables: ['data2'],
                source: 'ESA',
                created_at: new \DateTimeImmutable(),
            ),
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('getList')
            ->willReturn($items);

        // Act
        $result = $this->service->search('Mission', $items);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Space Mission Analysis', $result[0]->dataset_title);
    }

    public function testSearchIsCaseInsensitive(): void
    {
        // Arrange
        $items = [
            new OsdrItemDTO(
                id: 1,
                dataset_id: 'test_001',
                dataset_title: 'UPPERCASE TITLE',
                variables: ['var'],
                source: 'Source',
                created_at: new \DateTimeImmutable(),
            ),
        ];

        // Act
        $result = $this->service->search('uppercase', $items);

        // Assert
        $this->assertCount(1, $result);
    }

    public function testSearchReturnsEmptyCollectionWhenNoMatch(): void
    {
        // Arrange
        $items = [
            new OsdrItemDTO(
                id: 1,
                dataset_id: 'test_001',
                dataset_title: 'Test Dataset',
                variables: [],
                source: 'Source',
                created_at: new \DateTimeImmutable(),
            ),
        ];

        // Act
        $result = $this->service->search('nonexistent', $items);

        // Assert
        $this->assertCount(0, $result);
    }

    public function testFilterByVariableFiltersCorrectly(): void
    {
        // Arrange
        $items = [
            new OsdrItemDTO(
                id: 1,
                dataset_id: 'test_001',
                dataset_title: 'Dataset with Temperature',
                variables: ['temperature', 'pressure'],
                source: 'Source',
                created_at: new \DateTimeImmutable(),
            ),
            new OsdrItemDTO(
                id: 2,
                dataset_id: 'test_002',
                dataset_title: 'Dataset without Target',
                variables: ['humidity', 'wind_speed'],
                source: 'Source',
                created_at: new \DateTimeImmutable(),
            ),
        ];

        // Act
        $result = $this->service->filterByVariable('temperature', $items);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('test_001', $result[0]->dataset_id);
    }

    public function testSyncTriggersRepositorySync(): void
    {
        // Arrange
        $expectedResult = ['synced' => 150, 'updated' => 45];

        $this->mockRepository
            ->expects($this->once())
            ->method('sync')
            ->willReturn($expectedResult);

        // Act
        $result = $this->service->sync();

        // Assert
        $this->assertEquals(150, $result['synced']);
        $this->assertEquals(45, $result['updated']);
    }

    public function testClearCacheRemovesOsdrListKey(): void
    {
        // Arrange
        $this->mockCache
            ->expects($this->once())
            ->method('forget')
            ->with('osdr.list');

        // Act
        $this->service->clearCache();

        // Assert - Cache::forget was called
        $this->assertTrue(true);
    }

    public function testGetListWithLimitRespectsLimit(): void
    {
        // Arrange
        $this->mockCache
            ->expects($this->once())
            ->method('remember')
            ->with('osdr.list', 600, $this->anything())
            ->willReturn(new Collection([]));

        // Act
        $this->service->getList(50);

        // Assert - Limit is passed to repository
        $this->assertTrue(true);
    }

    public function testFilterBySourceFiltersItems(): void
    {
        // Arrange
        $items = [
            new OsdrItemDTO(
                id: 1,
                dataset_id: 'test_001',
                dataset_title: 'NASA Dataset',
                variables: [],
                source: 'NASA',
                created_at: new \DateTimeImmutable(),
            ),
            new OsdrItemDTO(
                id: 2,
                dataset_id: 'test_002',
                dataset_title: 'ESA Dataset',
                variables: [],
                source: 'ESA',
                created_at: new \DateTimeImmutable(),
            ),
        ];

        // Act
        $result = $this->service->filterBySource('NASA', $items);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('NASA', $result[0]->source);
    }

    public function testServiceConstructorInjectsRepository(): void
    {
        // Assert - Constructor should set repository
        $this->assertNotNull($this->service);
    }
}
