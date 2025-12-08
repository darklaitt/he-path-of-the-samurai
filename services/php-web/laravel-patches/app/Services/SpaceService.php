<?php

namespace App\Services;

use App\Repositories\SpaceRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Space Service - единая точка доступа к космическим данным
 * Агрегирует данные из разных источников
 */
class SpaceService
{
    private const CACHE_TTL = 600; // 10 минут
    private const CACHE_KEY_SUMMARY = 'space.summary';

    public function __construct(
        private SpaceRepository $repository
    ) {}

    /**
     * Получить summary по всем источникам
     */
    public function getSummary(): array
    {
        return Cache::remember(
            self::CACHE_KEY_SUMMARY,
            self::CACHE_TTL,
            fn() => $this->repository->getSummary()
        );
    }

    /**
     * Получить последние данные по источнику
     */
    public function getLatestBySource(string $source): array
    {
        return Cache::remember(
            "space.{$source}.latest",
            self::CACHE_TTL,
            fn() => $this->repository->getLatest($source)
        );
    }

    /**
     * Refresh конкретного источника
     */
    public function refreshSource(string $source): bool
    {
        $result = $this->repository->refresh($source);
        Cache::forget("space.{$source}.latest");
        Cache::forget(self::CACHE_KEY_SUMMARY);
        return $result;
    }

    /**
     * Список доступных источников
     */
    public function getAvailableSources(): array
    {
        return [
            'iss' => 'International Space Station',
            'nasa_apod' => 'NASA - Astronomy Picture of the Day',
            'nasa_neo' => 'NASA - Near Earth Objects',
            'nasa_donki' => 'NOAA - Space Weather Events',
            'osdr' => 'NASA - Open Science Data Repository',
            'spacex' => 'SpaceX - Rockets & Launches',
        ];
    }

    /**
     * Получить статус источника
     */
    public function getSourceStatus(string $source): array
    {
        $data = $this->getLatestBySource($source);
        
        return [
            'source' => $source,
            'last_updated' => $data['fetched_at'] ?? null,
            'data_count' => count($data['payload'] ?? []),
            'is_recent' => $this->isRecent($data['fetched_at'] ?? null),
        ];
    }

    private function isRecent(?string $timestamp): bool
    {
        if (!$timestamp) {
            return false;
        }

        try {
            $date = new \DateTime($timestamp);
            $now = new \DateTime();
            $diff = $now->getTimestamp() - $date->getTimestamp();
            
            return $diff < 3600; // Менее часа назад
        } catch (\Exception $e) {
            return false;
        }
    }
}
