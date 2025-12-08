<?php

namespace App\Repositories;

use App\DTO\OsdrItemDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * OSDR Repository - работа с OSDR данными из Rust сервиса
 */
class OsdrRepository
{
    private string $baseUrl;
    private int $timeout = 10;

    public function __construct()
    {
        $this->baseUrl = env('RUST_BASE', 'http://rust_iss:3000');
    }

    /**
     * Получить список OSDR items
     */
    public function getList(int $limit = 20): array
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/osdr/list', ['limit' => $limit]);

            if ($response->successful()) {
                $data = $response->json('data.items', []);
                return array_map(
                    fn($item) => OsdrItemDTO::fromArray($item),
                    $data
                );
            }
        } catch (\Exception $e) {
            \Log::warning('OSDR List error: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Синхронизировать OSDR (trigger)
     */
    public function sync(): int
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/osdr/sync');

            if ($response->successful()) {
                return $response->json('data.written', 0);
            }
        } catch (\Exception $e) {
            \Log::warning('OSDR Sync error: ' . $e->getMessage());
        }

        return 0;
    }

    private function client(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->retry(2, 100)
            ->connectTimeout(10);
    }
}
