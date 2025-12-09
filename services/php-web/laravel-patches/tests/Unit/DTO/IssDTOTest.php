<?php

namespace Tests\Unit\DTO;

use App\DTO\IssDTO;
use PHPUnit\Framework\TestCase;

class IssDTOTest extends TestCase
{
    public function testIssDTOPropertiesAreReadonly(): void
    {
        // Arrange
        $dto = new IssDTO(
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

        // Assert - Properties should be accessible
        $this->assertEquals(1, $dto->id);
        $this->assertEquals('https://api.wheretheiss.at/v1/satellites/25544', $dto->source_url);
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->fetched_at);
    }

    public function testIssSTOCannotBeModified(): void
    {
        // Arrange
        $dto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable(),
            source_url: 'test',
            payload: []
        );

        // Assert - Try to modify (should fail)
        $this->expectException(\Error::class);
        $dto->id = 2;
    }

    public function testIssDTOContainsValidPayload(): void
    {
        // Arrange
        $payload = [
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'altitude' => 408.5,
            'velocity' => 7.66,
        ];

        $dto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable(),
            source_url: 'test',
            payload: $payload
        );

        // Assert
        $this->assertEquals($payload, $dto->payload);
        $this->assertArrayHasKey('latitude', $dto->payload);
        $this->assertEquals(51.5074, $dto->payload['latitude']);
    }

    public function testIssDTOFetched_atIsDateTimeImmutable(): void
    {
        // Arrange
        $now = new \DateTimeImmutable('2025-01-15 10:30:00');
        
        $dto = new IssDTO(
            id: 1,
            fetched_at: $now,
            source_url: 'test',
            payload: []
        );

        // Assert
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->fetched_at);
        $this->assertEquals('2025-01-15 10:30:00', $dto->fetched_at->format('Y-m-d H:i:s'));
    }

    public function testIssDTOGetLatitudeExtractsFromPayload(): void
    {
        // Arrange
        $dto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable(),
            source_url: 'test',
            payload: ['latitude' => 51.5074]
        );

        // Assert
        $this->assertEquals(51.5074, $dto->getLatitude());
    }

    public function testIssDTOGetLongitudeExtractsFromPayload(): void
    {
        // Arrange
        $dto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable(),
            source_url: 'test',
            payload: ['longitude' => -0.1278]
        );

        // Assert
        $this->assertEquals(-0.1278, $dto->getLongitude());
    }

    public function testIssDTOGetAltitudeExtractsFromPayload(): void
    {
        // Arrange
        $dto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable(),
            source_url: 'test',
            payload: ['altitude' => 408.5]
        );

        // Assert
        $this->assertEquals(408.5, $dto->getAltitude());
    }

    public function testIssDTOGetVelocityExtractsFromPayload(): void
    {
        // Arrange
        $dto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable(),
            source_url: 'test',
            payload: ['velocity' => 7.66]
        );

        // Assert
        $this->assertEquals(7.66, $dto->getVelocity());
    }

    public function testIssDTOHandlesMissingPayloadKeys(): void
    {
        // Arrange
        $dto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable(),
            source_url: 'test',
            payload: [] // Empty payload
        );

        // Assert - Should return null for missing keys
        $this->assertNull($dto->getLatitude());
        $this->assertNull($dto->getLongitude());
    }

    public function testIssDTOFromArrayFactory(): void
    {
        // Arrange
        $data = [
            'id' => 1,
            'fetched_at' => '2025-01-15T10:30:00Z',
            'source_url' => 'https://api.wheretheiss.at',
            'payload' => ['latitude' => 51.5],
        ];

        // Act
        $dto = IssDTO::fromArray($data);

        // Assert
        $this->assertEquals(1, $dto->id);
        $this->assertEquals('https://api.wheretheiss.at', $dto->source_url);
    }

    public function testIssDTOToArrayConversion(): void
    {
        // Arrange
        $dto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable('2025-01-15 10:30:00'),
            source_url: 'test',
            payload: ['latitude' => 51.5]
        );

        // Act
        $array = $dto->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('fetched_at', $array);
    }

    public function testIssDTOIdIsInteger(): void
    {
        // Arrange
        $dto = new IssDTO(
            id: 42,
            fetched_at: new \DateTimeImmutable(),
            source_url: 'test',
            payload: []
        );

        // Assert
        $this->assertIsInt($dto->id);
        $this->assertEquals(42, $dto->id);
    }

    public function testIssDTOSourceUrlIsString(): void
    {
        // Arrange
        $url = 'https://api.example.com/data';
        
        $dto = new IssDTO(
            id: 1,
            fetched_at: new \DateTimeImmutable(),
            source_url: $url,
            payload: []
        );

        // Assert
        $this->assertIsString($dto->source_url);
        $this->assertEquals($url, $dto->source_url);
    }
}
