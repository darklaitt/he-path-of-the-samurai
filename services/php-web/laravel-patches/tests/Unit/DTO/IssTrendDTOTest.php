<?php

namespace Tests\Unit\DTO;

use App\DTO\IssTrendDTO;
use PHPUnit\Framework\TestCase;

class IssTrendDTOTest extends TestCase
{
    public function testIssTrendDTOPropertiesAreReadonly(): void
    {
        // Arrange
        $dto = new IssTrendDTO(
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

        // Assert
        $this->assertEquals(12.5, $dto->delta_km);
        $this->assertEquals(100, $dto->dt_sec);
        $this->assertEquals(27.6, $dto->velocity_kmh);
    }

    public function testIssTrendDTOCannotBeModified(): void
    {
        // Arrange
        $dto = new IssTrendDTO(
            movement: [],
            delta_km: 10.0,
            dt_sec: 100,
            velocity_kmh: 20.0,
            from_time: new \DateTimeImmutable(),
            to_time: new \DateTimeImmutable(),
        );

        // Assert - Try to modify (should fail)
        $this->expectException(\Error::class);
        $dto->delta_km = 20.0;
    }

    public function testIssTrendDTOMovementIsArray(): void
    {
        // Arrange
        $movement = [
            ['lat' => 51.5, 'lon' => -0.1, 'ts' => 1705319400],
            ['lat' => 51.51, 'lon' => -0.11, 'ts' => 1705319500],
        ];

        $dto = new IssTrendDTO(
            movement: $movement,
            delta_km: 12.5,
            dt_sec: 100,
            velocity_kmh: 27.6,
            from_time: new \DateTimeImmutable(),
            to_time: new \DateTimeImmutable(),
        );

        // Assert
        $this->assertIsArray($dto->movement);
        $this->assertCount(2, $dto->movement);
    }

    public function testIssTrendDTODeltaKmIsFloat(): void
    {
        // Arrange
        $dto = new IssTrendDTO(
            movement: [],
            delta_km: 123.456,
            dt_sec: 100,
            velocity_kmh: 30.5,
            from_time: new \DateTimeImmutable(),
            to_time: new \DateTimeImmutable(),
        );

        // Assert
        $this->assertIsFloat($dto->delta_km);
        $this->assertEquals(123.456, $dto->delta_km, 0.001);
    }

    public function testIssTrendDTODtSecIsInteger(): void
    {
        // Arrange
        $dto = new IssTrendDTO(
            movement: [],
            delta_km: 10.0,
            dt_sec: 360,
            velocity_kmh: 20.0,
            from_time: new \DateTimeImmutable(),
            to_time: new \DateTimeImmutable(),
        );

        // Assert
        $this->assertIsInt($dto->dt_sec);
        $this->assertEquals(360, $dto->dt_sec);
    }

    public function testIssTrendDTOVelocityKmhIsFloat(): void
    {
        // Arrange
        $dto = new IssTrendDTO(
            movement: [],
            delta_km: 10.0,
            dt_sec: 100,
            velocity_kmh: 28.8,
            from_time: new \DateTimeImmutable(),
            to_time: new \DateTimeImmutable(),
        );

        // Assert
        $this->assertIsFloat($dto->velocity_kmh);
        $this->assertEquals(28.8, $dto->velocity_kmh);
    }

    public function testIssTrendDTOFromTimeIsDateTimeImmutable(): void
    {
        // Arrange
        $fromTime = new \DateTimeImmutable('2025-01-15 10:30:00');
        
        $dto = new IssTrendDTO(
            movement: [],
            delta_km: 10.0,
            dt_sec: 100,
            velocity_kmh: 20.0,
            from_time: $fromTime,
            to_time: new \DateTimeImmutable('2025-01-15 10:31:40'),
        );

        // Assert
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->from_time);
        $this->assertEquals('2025-01-15 10:30:00', $dto->from_time->format('Y-m-d H:i:s'));
    }

    public function testIssTrendDTOToTimeIsDateTimeImmutable(): void
    {
        // Arrange
        $toTime = new \DateTimeImmutable('2025-01-15 10:31:40');
        
        $dto = new IssTrendDTO(
            movement: [],
            delta_km: 10.0,
            dt_sec: 100,
            velocity_kmh: 20.0,
            from_time: new \DateTimeImmutable('2025-01-15 10:30:00'),
            to_time: $toTime,
        );

        // Assert
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->to_time);
        $this->assertEquals('2025-01-15 10:31:40', $dto->to_time->format('Y-m-d H:i:s'));
    }

    public function testGetAltitudeChangeCalculatesCorrectly(): void
    {
        // Arrange
        $dto = new IssTrendDTO(
            movement: [
                ['altitude' => 400.0, 'lat' => 51.5, 'lon' => -0.1, 'ts' => 1705319400],
                ['altitude' => 410.5, 'lat' => 51.51, 'lon' => -0.11, 'ts' => 1705319500],
            ],
            delta_km: 12.5,
            dt_sec: 100,
            velocity_kmh: 27.6,
            from_time: new \DateTimeImmutable(),
            to_time: new \DateTimeImmutable(),
        );

        // Act
        $change = $dto->getAltitudeChange();

        // Assert
        $this->assertIsFloat($change);
        $this->assertEquals(10.5, $change, 0.1);
    }

    public function testGetTravelDistanceReturnsDeltaKm(): void
    {
        // Arrange
        $dto = new IssTrendDTO(
            movement: [],
            delta_km: 456.789,
            dt_sec: 100,
            velocity_kmh: 20.0,
            from_time: new \DateTimeImmutable(),
            to_time: new \DateTimeImmutable(),
        );

        // Act
        $distance = $dto->getTravelDistance();

        // Assert
        $this->assertIsFloat($distance);
        $this->assertEquals(456.789, $distance);
    }

    public function testIssTrendDTOFromArrayFactory(): void
    {
        // Arrange
        $data = [
            'movement' => [
                ['lat' => 51.5, 'lon' => -0.1, 'ts' => 1705319400],
            ],
            'delta_km' => 12.5,
            'dt_sec' => 100,
            'velocity_kmh' => 27.6,
            'from_time' => '2025-01-15T10:30:00Z',
            'to_time' => '2025-01-15T10:31:40Z',
        ];

        // Act
        $dto = IssTrendDTO::fromArray($data);

        // Assert
        $this->assertEquals(12.5, $dto->delta_km);
        $this->assertEquals(100, $dto->dt_sec);
    }

    public function testIssTrendDTOToArrayConversion(): void
    {
        // Arrange
        $dto = new IssTrendDTO(
            movement: [['lat' => 51.5, 'lon' => -0.1, 'ts' => 1705319400]],
            delta_km: 12.5,
            dt_sec: 100,
            velocity_kmh: 27.6,
            from_time: new \DateTimeImmutable('2025-01-15 10:30:00'),
            to_time: new \DateTimeImmutable('2025-01-15 10:31:40'),
        );

        // Act
        $array = $dto->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('delta_km', $array);
        $this->assertArrayHasKey('movement', $array);
        $this->assertArrayHasKey('from_time', $array);
    }

    public function testIssTrendDTOWithEmptyMovement(): void
    {
        // Arrange
        $dto = new IssTrendDTO(
            movement: [],
            delta_km: 0.0,
            dt_sec: 0,
            velocity_kmh: 0.0,
            from_time: new \DateTimeImmutable(),
            to_time: new \DateTimeImmutable(),
        );

        // Assert
        $this->assertEmpty($dto->movement);
        $this->assertEquals(0.0, $dto->delta_km);
    }

    public function testIssTrendDTOMovementPointsHaveCoordinates(): void
    {
        // Arrange
        $movement = [
            ['lat' => 51.5, 'lon' => -0.1, 'ts' => 1705319400],
            ['lat' => 51.51, 'lon' => -0.11, 'ts' => 1705319500],
            ['lat' => 51.52, 'lon' => -0.12, 'ts' => 1705319600],
        ];

        $dto = new IssTrendDTO(
            movement: $movement,
            delta_km: 25.0,
            dt_sec: 200,
            velocity_kmh: 27.5,
            from_time: new \DateTimeImmutable(),
            to_time: new \DateTimeImmutable(),
        );

        // Assert
        $this->assertCount(3, $dto->movement);
        foreach ($dto->movement as $point) {
            $this->assertArrayHasKey('lat', $point);
            $this->assertArrayHasKey('lon', $point);
            $this->assertArrayHasKey('ts', $point);
        }
    }
}
