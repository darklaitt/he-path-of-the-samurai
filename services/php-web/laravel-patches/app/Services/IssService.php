<?php

namespace App\Services;

use App\DTO\IssDTO;
use App\DTO\IssTrendDTO;
use App\Repositories\IssRepository;
use Illuminate\Support\Facades\Cache;

/**
 * ISS Service - бизнес-логика работы с МКС
 * Обрабатывает кэширование, валидацию и трансформацию данных
 */
class IssService
{
    private const CACHE_TTL = 300; // 5 минут
    private const CACHE_KEY_LAST = 'iss.last';
    private const CACHE_KEY_TREND = 'iss.trend';

    public function __construct(
        private IssRepository $repository
    ) {}

    /**
     * Получить последние данные МКС (с кэшем)
     */
    public function getLastIss(): ?IssDTO
    {
        return Cache::remember(
            self::CACHE_KEY_LAST,
            self::CACHE_TTL,
            fn() => $this->repository->getLastIss()
        );
    }

    /**
     * Получить тренд МКС (с кэшем)
     */
    public function getTrend(): ?IssTrendDTO
    {
        $trend = Cache::remember(
            self::CACHE_KEY_TREND,
            self::CACHE_TTL,
            fn() => $this->repository->getIssTrend()
        );

        if ($trend && is_array($trend)) {
            return IssTrendDTO::fromArray($trend);
        }

        return null;
    }

    /**
     * Принудительное обновление МКС
     * Очищает кэш и получает свежие данные
     */
    public function refreshLastIss(): ?IssDTO
    {
        Cache::forget(self::CACHE_KEY_LAST);
        Cache::forget(self::CACHE_KEY_TREND);

        return $this->repository->triggerFetch();
    }

    /**
     * Получить информационные параметры МКС
     */
    public function getIssInfo(): array
    {
        return [
            'name' => 'International Space Station',
            'crew_capacity' => 7,
            'mass_kg' => 420_000,
            'orbital_period' => '92.68 minutes',
            'avg_velocity' => '27_600 km/h',
        ];
    }
}
