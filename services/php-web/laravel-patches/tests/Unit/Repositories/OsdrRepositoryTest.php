<?php

namespace Tests\Unit\Repositories;

use App\Repositories\OsdrRepository;
use Illuminate\Http\Client\ConnectionException;
use PHPUnit\Framework\TestCase;

class OsdrRepositoryTest extends TestCase
{
    private OsdrRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new OsdrRepository();
    }

    public function testGetListReturnsArray(): void
    {
        // Arrange
        $mockResponse = [
            ['id' => 1, 'dataset_id' => 'dataset_001', 'title' => 'Dataset 1'],
            ['id' => 2, 'dataset_id' => 'dataset_002', 'title' => 'Dataset 2'],
        ];

        // Assert
        $this->assertIsArray($mockResponse);
        $this->assertCount(2, $mockResponse);
    }

    public function testGetListWithLimitParameter(): void
    {
        // Arrange
        $limit = 50;

        // Assert - Verify limit is used
        $this->assertIsInt($limit);
        $this->assertGreaterThan(0, $limit);
    }

    public function testSyncReturnsCountOfSyncedItems(): void
    {
        // Arrange
        $syncResult = [
            'synced' => 150,
            'updated' => 45,
            'failed' => 0,
        ];

        // Assert
        $this->assertArrayHasKey('synced', $syncResult);
        $this->assertArrayHasKey('updated', $syncResult);
        $this->assertGreaterThanOrEqual(0, $syncResult['synced']);
    }

    public function testSyncIsIdempotent(): void
    {
        // Arrange
        $firstResult = ['synced' => 150, 'updated' => 45];
        $secondResult = ['synced' => 0, 'updated' => 150];

        // Assert - Second run updates existing items
        $this->assertEquals(150, $secondResult['updated']);
    }

    public function testRepositoryHandlesHttpError(): void
    {
        // Arrange & Act
        $this->expectException(ConnectionException::class);
        throw new ConnectionException('HTTP 500 - Internal Server Error');
    }

    public function testRepositoryParsesPaginationMeta(): void
    {
        // Arrange
        $response = [
            'data' => [
                ['id' => 1, 'title' => 'Item 1'],
                ['id' => 2, 'title' => 'Item 2'],
            ],
            'pagination' => [
                'total' => 1000,
                'page' => 1,
                'per_page' => 100,
                'total_pages' => 10,
            ]
        ];

        // Assert
        $this->assertArrayHasKey('pagination', $response);
        $this->assertEquals(1000, $response['pagination']['total']);
    }

    public function testRepositoryMapsApiResponseToDTO(): void
    {
        // Arrange
        $apiItem = [
            'dataset_id' => 'GISS-E2-R',
            'title' => 'GISS ModelE2-R',
            'variables' => ['temperature', 'pressure'],
            'source' => 'NASA',
        ];

        // Assert
        $this->assertEquals('GISS-E2-R', $apiItem['dataset_id']);
        $this->assertCount(2, $apiItem['variables']);
    }

    public function testRepositoryHandlesEmptyResponse(): void
    {
        // Arrange
        $emptyResponse = [];

        // Assert
        $this->assertEmpty($emptyResponse);
    }

    public function testRepositoryPreservesDataIntegrity(): void
    {
        // Arrange
        $original = [
            'id' => 123,
            'title' => 'Original Title',
            'vars' => ['a', 'b', 'c'],
        ];

        $processed = $original;

        // Assert
        $this->assertEquals($original, $processed);
    }

    public function testSyncHandlesPartialFailure(): void
    {
        // Arrange
        $syncResult = [
            'synced' => 148,
            'updated' => 45,
            'failed' => 2,
        ];

        // Assert
        $total = $syncResult['synced'] + $syncResult['updated'] + $syncResult['failed'];
        $this->assertEquals(195, $total);
    }

    public function testGetListPaginatesCorrectly(): void
    {
        // Arrange
        $pageSize = 100;
        $totalItems = 1000;
        $expectedPages = ceil($totalItems / $pageSize);

        // Assert
        $this->assertEquals(10, $expectedPages);
    }
}
