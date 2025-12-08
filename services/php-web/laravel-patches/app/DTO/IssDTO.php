<?php

namespace App\DTO;

/**
 * ISS Data Transfer Object
 */
class IssDTO
{
    public function __construct(
        public readonly int $id,
        public readonly \DateTimeImmutable $fetched_at,
        public readonly string $source_url,
        public readonly array $payload,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? 0,
            fetched_at: isset($data['fetched_at']) ? new \DateTimeImmutable($data['fetched_at']) : new \DateTimeImmutable(),
            source_url: $data['source_url'] ?? '',
            payload: $data['payload'] ?? [],
        );
    }

    public function getLatitude(): ?float
    {
        return $this->payload['latitude'] ?? null;
    }

    public function getLongitude(): ?float
    {
        return $this->payload['longitude'] ?? null;
    }

    public function getVelocity(): ?float
    {
        return $this->payload['velocity'] ?? null;
    }

    public function getAltitude(): ?float
    {
        return $this->payload['altitude'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fetched_at' => $this->fetched_at->format('c'),
            'source_url' => $this->source_url,
            'payload' => $this->payload,
        ];
    }
}
