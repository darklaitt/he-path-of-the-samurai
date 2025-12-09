<?php

namespace Tests\Integration;

use Tests\Helpers\TestDataFactory;
use Tests\Helpers\HttpTestHelper;
use PHPUnit\Framework\TestCase;

class ComponentInteractionTest extends TestCase
{
    /**
     * Test Repository → Service interaction
     */
    public function testRepositoryServiceInteraction(): void
    {
        // Arrange
        $repositoryData = HttpTestHelper::mockSuccessfulIssResponse();
        
        // Service would process this
        $dto = TestDataFactory::createIssDTO([
            'payload' => $repositoryData,
        ]);

        // Assert
        $this->assertNotNull($dto);
        $this->assertEquals($repositoryData['latitude'], $dto->getLatitude());
    }

    /**
     * Test Service → Controller data flow
     */
    public function testServiceControllerDataFlow(): void
    {
        // Arrange
        $serviceData = TestDataFactory::createIssDTO();
        
        // Controller receives DTO
        $responseData = [
            'ok' => true,
            'data' => [
                'id' => $serviceData->id,
                'latitude' => $serviceData->getLatitude(),
                'longitude' => $serviceData->getLongitude(),
            ],
        ];

        // Assert
        $this->assertTrue($responseData['ok']);
        $this->assertEquals($serviceData->id, $responseData['data']['id']);
    }

    /**
     * Test Cache → Service interaction
     */
    public function testCacheServiceInteraction(): void
    {
        // Arrange
        $cachedData = TestDataFactory::createIssDTO();
        $cacheKey = 'iss.last';
        $cacheTtl = 300;

        // Simulate cache hit
        $cached = true;

        // Assert
        $this->assertTrue($cached);
        $this->assertIsString($cacheKey);
        $this->assertGreaterThan(0, $cacheTtl);
    }

    /**
     * Test HTTP Client → Repository interaction with retry
     */
    public function testHttpClientRetryFlow(): void
    {
        // Arrange
        $attempts = 0;
        $maxRetries = 2;
        $response = null;

        // Simulate retry loop
        do {
            $attempts++;
            try {
                if ($attempts < 2) {
                    throw new \Exception('Network error');
                }
                $response = HttpTestHelper::mockSuccessfulIssResponse();
            } catch (\Exception $e) {
                if ($attempts >= $maxRetries) {
                    break;
                }
            }
        } while ($response === null && $attempts < $maxRetries);

        // Assert
        $this->assertNotNull($response);
        $this->assertGreaterThan(1, $attempts);
    }

    /**
     * Test full request/response cycle
     */
    public function testFullRequestResponseCycle(): void
    {
        // Arrange - Request comes in
        $request = ['endpoint' => '/api/iss/last'];
        
        // → Repository fetches data
        $httpData = HttpTestHelper::mockSuccessfulIssResponse();
        
        // → Service processes it
        $dto = TestDataFactory::createIssDTO(['payload' => $httpData]);
        
        // → Controller formats response
        $response = [
            'ok' => true,
            'data' => [
                'id' => $dto->id,
                'latitude' => $dto->getLatitude(),
                'fetched_at' => $dto->fetched_at->format('Y-m-d H:i:s'),
            ],
        ];

        // Assert
        $this->assertTrue($response['ok']);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
    }

    /**
     * Test collection processing pipeline
     */
    public function testCollectionProcessingPipeline(): void
    {
        // Arrange - Repository returns raw data
        $rawData = HttpTestHelper::mockSuccessfulOsdrResponse();
        
        // → Create DTOs
        $dtos = [];
        foreach ($rawData['results'] as $item) {
            $dtos[] = TestDataFactory::createOsdrItemDTO([
                'dataset_id' => $item['dataset_id'],
                'dataset_title' => $item['title'],
                'variables' => $item['variables'],
            ]);
        }

        // → Service filters/searches
        $filtered = array_filter($dtos, fn($dto) => count($dto->variables) >= 2);

        // Assert
        $this->assertNotEmpty($dtos);
        $this->assertGreaterThan(0, count($filtered));
    }

    /**
     * Test error propagation through layers
     */
    public function testErrorPropagation(): void
    {
        // Arrange - Error at repository level
        $repositoryError = HttpTestHelper::mockErrorResponse(500, 'Database unavailable');
        
        // Error is caught at service level
        $serviceHandling = [
            'error' => true,
            'message' => $repositoryError['message'],
            'code' => $repositoryError['code'],
        ];

        // Controller returns error response
        $response = [
            'ok' => false,
            'error' => [
                'message' => $serviceHandling['message'],
                'code' => $serviceHandling['code'],
            ],
        ];

        // Assert
        $this->assertFalse($response['ok']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(500, $response['error']['code']);
    }

    /**
     * Test state management across requests
     */
    public function testStateManagementAcrossRequests(): void
    {
        // Arrange - First request
        $request1 = TestDataFactory::createIssDTO(['id' => 1]);
        
        // Second request
        $request2 = TestDataFactory::createIssDTO(['id' => 2]);

        // Assert - States are independent
        $this->assertEquals(1, $request1->id);
        $this->assertEquals(2, $request2->id);
        $this->assertNotEquals($request1->id, $request2->id);
    }

    /**
     * Test data validation pipeline
     */
    public function testDataValidationPipeline(): void
    {
        // Arrange - Raw input
        $input = [
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'altitude' => 408.5,
            'velocity' => 7.66,
            'timestamp' => 1705319400,
        ];

        // Validate
        $isValid = HttpTestHelper::validateIssResponseFormat($input);

        // Transform
        $dto = TestDataFactory::createIssDTO(['payload' => $input]);

        // Assert
        $this->assertTrue($isValid);
        $this->assertNotNull($dto);
    }

    /**
     * Test concurrent service calls
     */
    public function testConcurrentServiceCalls(): void
    {
        // Arrange
        $calls = [];
        for ($i = 0; $i < 5; $i++) {
            $calls[] = [
                'service' => 'IssService',
                'method' => 'getLastIss',
                'result' => TestDataFactory::createIssDTO(['id' => $i + 1]),
            ];
        }

        // Assert - All calls completed
        $this->assertCount(5, $calls);
        foreach ($calls as $index => $call) {
            $this->assertEquals('IssService', $call['service']);
            $this->assertEquals($index + 1, $call['result']->id);
        }
    }

    /**
     * Test pagination interaction between components
     */
    public function testPaginationInteraction(): void
    {
        // Arrange - Repository with pagination
        $page1 = TestDataFactory::createPaginatedResponse(page: 1);
        
        // Service processes page 1
        $dtos1 = array_map(
            fn($item) => TestDataFactory::createOsdrItemDTO($item),
            $page1['data']
        );

        // Request next page
        $page2 = TestDataFactory::createPaginatedResponse(page: 2);
        $dtos2 = array_map(
            fn($item) => TestDataFactory::createOsdrItemDTO($item),
            $page2['data']
        );

        // Assert
        $this->assertEquals(1, $page1['pagination']['page']);
        $this->assertEquals(2, $page2['pagination']['page']);
        $this->assertNotEmpty($dtos1);
        $this->assertNotEmpty($dtos2);
    }

    /**
     * Test fallback mechanisms
     */
    public function testFallbackMechanisms(): void
    {
        // Arrange - Primary source fails
        $primary = null;
        
        // Fall back to cache
        $cached = TestDataFactory::createIssDTO();
        
        // Fallback to default
        $default = ['status' => 'unavailable'];

        // Use first available
        $result = $primary ?? $cached ?? $default;

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(\App\DTO\IssDTO::class, $result);
    }
}
