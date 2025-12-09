<?php

namespace Tests\Unit\DTO;

use App\DTO\OsdrItemDTO;
use PHPUnit\Framework\TestCase;

class OsdrItemDTOTest extends TestCase
{
    public function testOsdrItemDTOPropertiesAreReadonly(): void
    {
        // Arrange
        $dto = new OsdrItemDTO(
            id: 1,
            dataset_id: 'dataset_001',
            dataset_title: 'Space Mission Data',
            variables: ['temperature', 'pressure'],
            source: 'NASA OSDR',
            created_at: new \DateTimeImmutable('2025-01-15 10:00:00')
        );

        // Assert
        $this->assertEquals(1, $dto->id);
        $this->assertEquals('dataset_001', $dto->dataset_id);
        $this->assertEquals('Space Mission Data', $dto->dataset_title);
    }

    public function testOsdrItemDTOCannotBeModified(): void
    {
        // Arrange
        $dto = new OsdrItemDTO(
            id: 1,
            dataset_id: 'dataset_001',
            dataset_title: 'Test',
            variables: [],
            source: 'Test',
            created_at: new \DateTimeImmutable()
        );

        // Assert - Try to modify (should fail)
        $this->expectException(\Error::class);
        $dto->id = 2;
    }

    public function testOsdrItemDTOVariablesIsArray(): void
    {
        // Arrange
        $variables = ['temperature', 'pressure', 'humidity'];
        
        $dto = new OsdrItemDTO(
            id: 1,
            dataset_id: 'dataset_001',
            dataset_title: 'Test',
            variables: $variables,
            source: 'Source',
            created_at: new \DateTimeImmutable()
        );

        // Assert
        $this->assertIsArray($dto->variables);
        $this->assertCount(3, $dto->variables);
        $this->assertContains('temperature', $dto->variables);
    }

    public function testOsdrItemDTOCreatedAtIsDateTimeImmutable(): void
    {
        // Arrange
        $date = new \DateTimeImmutable('2025-01-15 10:00:00');
        
        $dto = new OsdrItemDTO(
            id: 1,
            dataset_id: 'dataset_001',
            dataset_title: 'Test',
            variables: [],
            source: 'Test',
            created_at: $date
        );

        // Assert
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->created_at);
        $this->assertEquals('2025-01-15 10:00:00', $dto->created_at->format('Y-m-d H:i:s'));
    }

    public function testGetRawRestUrlReturnsNasaUrl(): void
    {
        // Arrange
        $dto = new OsdrItemDTO(
            id: 1,
            dataset_id: 'GISS-E2-R',
            dataset_title: 'GISS ModelE2-R',
            variables: [],
            source: 'NASA',
            created_at: new \DateTimeImmutable()
        );

        // Act
        $url = $dto->getRawRestUrl();

        // Assert
        $this->assertIsString($url);
        $this->assertStringContainsString('osdr.nasa.gov', $url);
        $this->assertStringContainsString('GISS-E2-R', $url);
    }

    public function testGetVariableNamesReturnsFormattedList(): void
    {
        // Arrange
        $variables = ['temperature_celsius', 'pressure_hpa', 'humidity_percent'];
        
        $dto = new OsdrItemDTO(
            id: 1,
            dataset_id: 'dataset_001',
            dataset_title: 'Test',
            variables: $variables,
            source: 'Test',
            created_at: new \DateTimeImmutable()
        );

        // Act
        $names = $dto->getVariableNames();

        // Assert
        $this->assertIsArray($names);
        $this->assertCount(3, $names);
    }

    public function testOsdrItemDTOWithEmptyVariables(): void
    {
        // Arrange
        $dto = new OsdrItemDTO(
            id: 1,
            dataset_id: 'dataset_001',
            dataset_title: 'Test',
            variables: [],
            source: 'Test',
            created_at: new \DateTimeImmutable()
        );

        // Assert
        $this->assertEmpty($dto->variables);
        $this->assertCount(0, $dto->variables);
    }

    public function testOsdrItemDTOIdIsInteger(): void
    {
        // Arrange
        $dto = new OsdrItemDTO(
            id: 42,
            dataset_id: 'dataset_001',
            dataset_title: 'Test',
            variables: [],
            source: 'Test',
            created_at: new \DateTimeImmutable()
        );

        // Assert
        $this->assertIsInt($dto->id);
        $this->assertEquals(42, $dto->id);
    }

    public function testOsdrItemDTODatasetIdIsString(): void
    {
        // Arrange
        $datasetId = 'UNIQUE_DATASET_12345';
        
        $dto = new OsdrItemDTO(
            id: 1,
            dataset_id: $datasetId,
            dataset_title: 'Test',
            variables: [],
            source: 'Test',
            created_at: new \DateTimeImmutable()
        );

        // Assert
        $this->assertIsString($dto->dataset_id);
        $this->assertEquals($datasetId, $dto->dataset_id);
    }

    public function testOsdrItemDTODatasetTitleIsString(): void
    {
        // Arrange
        $title = 'Comprehensive Space Weather Database';
        
        $dto = new OsdrItemDTO(
            id: 1,
            dataset_id: 'dataset_001',
            dataset_title: $title,
            variables: [],
            source: 'Test',
            created_at: new \DateTimeImmutable()
        );

        // Assert
        $this->assertIsString($dto->dataset_title);
        $this->assertEquals($title, $dto->dataset_title);
    }

    public function testOsdrItemDTOSourceIsString(): void
    {
        // Arrange
        $source = 'European Space Agency';
        
        $dto = new OsdrItemDTO(
            id: 1,
            dataset_id: 'dataset_001',
            dataset_title: 'Test',
            variables: [],
            source: $source,
            created_at: new \DateTimeImmutable()
        );

        // Assert
        $this->assertIsString($dto->source);
        $this->assertEquals($source, $dto->source);
    }

    public function testOsdrItemDTOFromArrayFactory(): void
    {
        // Arrange
        $data = [
            'id' => 1,
            'dataset_id' => 'dataset_001',
            'dataset_title' => 'Test Dataset',
            'variables' => ['var1', 'var2'],
            'source' => 'NASA',
            'created_at' => '2025-01-15T10:00:00Z',
        ];

        // Act
        $dto = OsdrItemDTO::fromArray($data);

        // Assert
        $this->assertEquals(1, $dto->id);
        $this->assertEquals('dataset_001', $dto->dataset_id);
    }

    public function testOsdrItemDTOToArrayConversion(): void
    {
        // Arrange
        $dto = new OsdrItemDTO(
            id: 1,
            dataset_id: 'dataset_001',
            dataset_title: 'Test',
            variables: ['var1', 'var2'],
            source: 'NASA',
            created_at: new \DateTimeImmutable('2025-01-15 10:00:00')
        );

        // Act
        $array = $dto->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('dataset_id', $array);
        $this->assertArrayHasKey('variables', $array);
    }
}
