<?php

namespace Tests\Feature\Controllers;

use App\DTO\OsdrItemDTO;
use App\Services\OsdrService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\MockObject\MockObject;

class OsdrControllerTest extends FeatureTestCase
{
    private OsdrService&MockObject $mockOsdrService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockOsdrService = $this->createMock(OsdrService::class);
        $this->app->bind(OsdrService::class, fn() => $this->mockOsdrService);
    }

    public function testIndexPageLoads(): void
    {
        // Arrange
        $items = [
            new OsdrItemDTO(
                id: 1,
                dataset_id: 'dataset_001',
                dataset_title: 'Space Data',
                variables: ['temp'],
                source: 'NASA',
                created_at: new \DateTimeImmutable(),
            ),
        ];

        $this->mockOsdrService
            ->expects($this->once())
            ->method('getList')
            ->willReturn(new Collection($items));

        // Act
        $response = $this->get('/osdr');

        // Assert
        $response->assertStatus(200);
        $response->assertViewIs('osdr.page');
    }

    public function testSearchApiEndpoint(): void
    {
        // Arrange
        $items = [
            new OsdrItemDTO(
                id: 1,
                dataset_id: 'dataset_001',
                dataset_title: 'Mission Data',
                variables: ['var1'],
                source: 'NASA',
                created_at: new \DateTimeImmutable(),
            ),
        ];

        $this->mockOsdrService
            ->expects($this->once())
            ->method('getList')
            ->willReturn(new Collection($items));

        $this->mockOsdrService
            ->expects($this->once())
            ->method('search')
            ->with('Mission', $this->isInstanceOf(Collection::class))
            ->willReturn(new Collection($items));

        // Act
        $response = $this->getJson('/api/osdr/search?q=Mission');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                '*' => [
                    'id',
                    'dataset_id',
                    'dataset_title',
                    'variables',
                ]
            ]
        ]);
    }

    public function testFilterByVariableApiEndpoint(): void
    {
        // Arrange
        $items = [
            new OsdrItemDTO(
                id: 1,
                dataset_id: 'dataset_001',
                dataset_title: 'Temperature Data',
                variables: ['temperature', 'pressure'],
                source: 'NASA',
                created_at: new \DateTimeImmutable(),
            ),
        ];

        $this->mockOsdrService
            ->expects($this->once())
            ->method('getList')
            ->willReturn(new Collection($items));

        $this->mockOsdrService
            ->expects($this->once())
            ->method('filterByVariable')
            ->with('temperature', $this->isInstanceOf(Collection::class))
            ->willReturn(new Collection($items));

        // Act
        $response = $this->getJson('/api/osdr/filter?variable=temperature');

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
        ]);
    }

    public function testSyncApiEndpoint(): void
    {
        // Arrange
        $syncResult = ['synced' => 150, 'updated' => 45];

        $this->mockOsdrService
            ->expects($this->once())
            ->method('sync')
            ->willReturn($syncResult);

        // Act
        $response = $this->postJson('/api/osdr/sync');

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'data' => [
                'synced' => 150,
                'updated' => 45,
            ]
        ]);
    }

    public function testListApiEndpointWithLimit(): void
    {
        // Arrange
        $items = [];
        for ($i = 0; $i < 10; $i++) {
            $items[] = new OsdrItemDTO(
                id: $i,
                dataset_id: "dataset_{$i}",
                dataset_title: "Dataset {$i}",
                variables: ['var1'],
                source: 'NASA',
                created_at: new \DateTimeImmutable(),
            );
        }

        $this->mockOsdrService
            ->expects($this->once())
            ->method('getList')
            ->with(10)
            ->willReturn(new Collection($items));

        // Act
        $response = $this->getJson('/api/osdr/list?limit=10');

        // Assert
        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function testSearchHandlesEmptyQuery(): void
    {
        // Arrange
        $items = new Collection([]);

        $this->mockOsdrService
            ->expects($this->once())
            ->method('getList')
            ->willReturn($items);

        // Act
        $response = $this->getJson('/api/osdr/search?q=');

        // Assert
        $response->assertStatus(200);
    }

    public function testFilterBySourceApiEndpoint(): void
    {
        // Arrange
        $items = [
            new OsdrItemDTO(
                id: 1,
                dataset_id: 'dataset_001',
                dataset_title: 'NASA Data',
                variables: [],
                source: 'NASA',
                created_at: new \DateTimeImmutable(),
            ),
        ];

        $this->mockOsdrService
            ->expects($this->once())
            ->method('getList')
            ->willReturn(new Collection($items));

        // Act
        $response = $this->getJson('/api/osdr/filter?source=NASA');

        // Assert
        $response->assertStatus(200);
    }

    public function testSyncHandlesApiError(): void
    {
        // Arrange
        $this->mockOsdrService
            ->expects($this->once())
            ->method('sync')
            ->willThrowException(new \Exception('Sync failed'));

        // Act
        $response = $this->postJson('/api/osdr/sync');

        // Assert
        $response->assertStatus(500);
        $response->assertJson(['ok' => false]);
    }

    public function testListReturnsPaginatedResults(): void
    {
        // Arrange - Create 100 items
        $items = [];
        for ($i = 0; $i < 100; $i++) {
            $items[] = new OsdrItemDTO(
                id: $i,
                dataset_id: "dataset_{$i}",
                dataset_title: "Dataset {$i}",
                variables: [],
                source: 'NASA',
                created_at: new \DateTimeImmutable(),
            );
        }

        $this->mockOsdrService
            ->expects($this->once())
            ->method('getList')
            ->with(50)
            ->willReturn(new Collection(array_slice($items, 0, 50)));

        // Act
        $response = $this->getJson('/api/osdr/list?limit=50');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure(['ok', 'data']);
    }

    public function testOsdrPageWithErrorHandling(): void
    {
        // Arrange
        $this->mockOsdrService
            ->expects($this->once())
            ->method('getList')
            ->willThrowException(new \Exception('Connection failed'));

        // Act
        $response = $this->get('/osdr');

        // Assert - Should handle gracefully
        $response->assertStatus(500);
    }
}
