<?php

namespace App\DTO;

/**
 * OSDR Item Data Transfer Object
 */
class OsdrItemDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $dataset_id,
        public readonly ?string $title,
        public readonly ?string $status,
        public readonly ?\DateTimeImmutable $updated_at,
        public readonly \DateTimeImmutable $inserted_at,
        public readonly array $raw,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? 0,
            dataset_id: $data['dataset_id'] ?? null,
            title: $data['title'] ?? null,
            status: $data['status'] ?? null,
            updated_at: isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null,
            inserted_at: isset($data['inserted_at']) ? new \DateTimeImmutable($data['inserted_at']) : new \DateTimeImmutable(),
            raw: $data['raw'] ?? [],
        );
    }

    public function getRestUrl(): ?string
    {
        if (is_array($this->raw)) {
            return $this->raw['REST_URL'] ?? $this->raw['rest_url'] ?? $this->raw['rest'] ?? null;
        }
        return null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'dataset_id' => $this->dataset_id,
            'title' => $this->title,
            'status' => $this->status,
            'updated_at' => $this->updated_at?->format('c'),
            'inserted_at' => $this->inserted_at->format('c'),
            'raw' => $this->raw,
        ];
    }
}
