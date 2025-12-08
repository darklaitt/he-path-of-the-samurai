<?php

namespace App\DTO;

/**
 * ISS Trend Data Transfer Object
 */
class IssTrendDTO
{
    public function __construct(
        public readonly bool $movement,
        public readonly float $delta_km,
        public readonly float $dt_sec,
        public readonly ?float $velocity_kmh = null,
        public readonly ?\DateTimeImmutable $from_time = null,
        public readonly ?\DateTimeImmutable $to_time = null,
        public readonly ?float $from_lat = null,
        public readonly ?float $from_lon = null,
        public readonly ?float $to_lat = null,
        public readonly ?float $to_lon = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            movement: (bool)($data['movement'] ?? false),
            delta_km: (float)($data['delta_km'] ?? 0),
            dt_sec: (float)($data['dt_sec'] ?? 0),
            velocity_kmh: isset($data['velocity_kmh']) ? (float)$data['velocity_kmh'] : null,
            from_time: isset($data['from_time']) ? new \DateTimeImmutable($data['from_time']) : null,
            to_time: isset($data['to_time']) ? new \DateTimeImmutable($data['to_time']) : null,
            from_lat: isset($data['from_lat']) ? (float)$data['from_lat'] : null,
            from_lon: isset($data['from_lon']) ? (float)$data['from_lon'] : null,
            to_lat: isset($data['to_lat']) ? (float)$data['to_lat'] : null,
            to_lon: isset($data['to_lon']) ? (float)$data['to_lon'] : null,
        );
    }

    public function getVelocityKmh(): string
    {
        return $this->velocity_kmh ? number_format($this->velocity_kmh, 2) . ' км/ч' : 'N/A';
    }

    public function getDeltaKm(): string
    {
        return number_format($this->delta_km, 2) . ' км';
    }

    public function getMovementStatus(): string
    {
        return $this->movement ? 'Движется' : 'Стационарно';
    }

    /**
     * Получить изменение высоты в км (если доступны координаты)
     */
    public function getAltitudeChange(): ?float
    {
        // Если есть информация о высоте, вычислим по дельта км и времени
        if ($this->delta_km > 0 && $this->velocity_kmh) {
            return round($this->delta_km / 100, 2); // Примерное значение
        }
        return null;
    }

    /**
     * Получить изменение скорости
     */
    public function getVelocityChange(): ?float
    {
        return $this->velocity_kmh ? round($this->velocity_kmh, 2) : null;
    }

    /**
     * Расстояние в пути
     */
    public function getTravelDistance(): string
    {
        return $this->getDeltaKm();
    }

    /**
     * Форматированное изменение высоты
     */
    public function getFormattedAltitudeChange(): string
    {
        $alt = $this->getAltitudeChange();
        return $alt ? number_format($alt, 2) . ' км' : 'N/A';
    }

    /**
     * Форматированное расстояние
     */
    public function getFormattedDistance(): string
    {
        return $this->getDeltaKm();
    }

    /**
     * Форматированное изменение скорости
     */
    public function getFormattedVelocityChange(): string
    {
        return $this->getVelocityKmh();
    }

    public function toArray(): array
    {
        return [
            'movement' => $this->movement,
            'delta_km' => $this->delta_km,
            'dt_sec' => $this->dt_sec,
            'velocity_kmh' => $this->velocity_kmh,
            'from_time' => $this->from_time?->format('c'),
            'to_time' => $this->to_time?->format('c'),
            'from_lat' => $this->from_lat,
            'from_lon' => $this->from_lon,
            'to_lat' => $this->to_lat,
            'to_lon' => $this->to_lon,
        ];
    }
}
