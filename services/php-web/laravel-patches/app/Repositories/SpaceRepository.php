<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * Space Repository - работа с универсальным кэшем космических данных
 */
class SpaceRepository
{
    private string $baseUrl;
    private int $timeout = 10;

    public function __construct()
    {
        $this->baseUrl = env('RUST_BASE', 'http://rust_iss:3000');
    }

    /**
     * Получить последний кэш по источнику
     */
    public function getLatest(string $source): array
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/space/' . urlencode($source) . '/latest');

            if ($response->successful()) {
                return $response->json('data', []);
            }
        } catch (\Exception $e) {
            \Log::warning('Space Latest error for ' . $source . ': ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Refresh конкретного источника
     */
    public function refresh(string $source): bool
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/space/refresh', ['src' => $source]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::warning('Space Refresh error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Получить summary всех источников
     */
    public function getSummary(): array
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/space/summary');

            if ($response->successful()) {
                return $response->json('data', []);
            }
        } catch (\Exception $e) {
            \Log::warning('Space Summary error: ' . $e->getMessage());
        }

        return [];
    }

    private function client(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->retry(2, 100)
            ->connectTimeout(10);
    }
}
