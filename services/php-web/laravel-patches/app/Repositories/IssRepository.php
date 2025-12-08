<?php

namespace App\Repositories;

use App\DTO\IssDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * ISS Repository - работа с ISS данными из Rust сервиса
 */
class IssRepository
{
    private string $baseUrl;
    private int $timeout = 5;

    public function __construct()
    {
        $this->baseUrl = env('RUST_BASE', 'http://rust_iss:3000');
    }

    /**
     * Получить последние данные МКС
     */
    public function getLastIss(): ?IssDTO
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/last');

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data'])) {
                    return IssDTO::fromArray($data['data']);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('ISS Repository error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Получить тренд движения МКС
     */
    public function getIssTrend(): array
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/iss/trend');

            if ($response->successful()) {
                return $response->json('data', []);
            }
        } catch (\Exception $e) {
            \Log::warning('ISS Trend error: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Trigger ISS fetch (принудительное обновление)
     */
    public function triggerFetch(): ?IssDTO
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/fetch');

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data'])) {
                    return IssDTO::fromArray($data['data']);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('ISS Trigger error: ' . $e->getMessage());
        }

        return null;
    }

    private function client(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->retry(2, 100)
            ->connectTimeout($this->timeout);
    }
}
