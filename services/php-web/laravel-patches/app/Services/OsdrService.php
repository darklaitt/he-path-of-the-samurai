<?php

namespace App\Services;

use App\DTO\OsdrItemDTO;
use App\Repositories\OsdrRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * OSDR Service - бизнес-логика работы с OSDR
 * Фильтрация, сортировка, поиск
 */
class OsdrService
{
    private const CACHE_TTL = 600; // 10 минут
    private const CACHE_KEY = 'osdr.list';

    public function __construct(
        private OsdrRepository $repository
    ) {}

    /**
     * Получить список OSDR items
     */
    public function getList(int $limit = 20): Collection
    {
        $items = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn() => $this->repository->getList($limit)
        );

        return collect($items);
    }

    /**
     * Поиск по названию датасета
     */
    public function search(string $query): Collection
    {
        $items = $this->getList(100); // Берём больше на случай фильтрации

        return $items->filter(function (OsdrItemDTO $item) use ($query) {
            $query = strtolower($query);
            return strpos(strtolower($item->dataset_title), $query) !== false
                || strpos(strtolower($item->dataset_id), $query) !== false;
        });
    }

    /**
     * Фильтрация по типу переменной
     */
    public function filterByVariableType(string $type): Collection
    {
        $items = $this->getList(100);

        return $items->filter(function (OsdrItemDTO $item) use ($type) {
            $variables = array_keys($item->variables ?? []);
            return collect($variables)->contains(
                fn($var) => strpos($var, strtoupper($type)) !== false
            );
        });
    }

    /**
     * Синхронизировать базу данных
     */
    public function sync(): int
    {
        $written = $this->repository->sync();
        Cache::forget(self::CACHE_KEY);
        return $written;
    }

    /**
     * Очистить кэш
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
