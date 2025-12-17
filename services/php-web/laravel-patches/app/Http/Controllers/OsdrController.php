<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OsdrService;

/**
 * OsdrController - рефакторен для использования OsdrService
 * Вся бизнес-логика переведена в сервис
 */
class OsdrController extends Controller
{
    public function __construct(
        private OsdrService $osdrService
    ) {}

    public function index(Request $request)
    {
        $limit = min(max((int)$request->query('limit', 20), 1), 100);
        $query = trim((string)$request->query('q', ''));
        $sort  = (string)$request->query('sort', 'inserted_desc');
        
        // Получаем OsdrItemDTO через сервис с кэшированием и фильтрацией
        if ($query !== '') {
            $items = $this->osdrService->search($query)->take($limit);
        } else {
        $items = $this->osdrService->getList($limit);
        }
        
        // Преобразуем DTO в массив для обратной совместимости с Blade шаблонами
        $flatItems = $items->map(fn($item) => [
            'id'          => $item->id,
            'dataset_id'  => $item->dataset_id,
            'title'       => $item->title,
            'status'      => 'active',
            'updated_at'  => null,
            'inserted_at' => null,
            'rest_url'    => $item->getRestUrl(),
            'raw'         => is_string($item->raw) ? json_decode($item->raw, true) : $item->raw,
        ]);

        // Сортировка коллекции в памяти (для тренажёра достаточно)
        $flatItems = $flatItems->sortBy(function ($row) use ($sort) {
            return match ($sort) {
                'title_asc', 'title_desc'         => mb_strtolower($row['title'] ?? ''),
                'inserted_asc', 'inserted_desc'   => $row['inserted_at'] ?? '',
                default                           => $row['id'],
            };
        }, SORT_REGULAR, in_array($sort, ['title_desc', 'inserted_desc'], true));

        $flatItems = $flatItems->values()->toArray();

        return view('osdr', [
            'items' => $flatItems,
            'src'   => $query !== ''
                ? 'Service: OsdrService::search("' . $query . '")'
                : 'Service: OsdrService::getList(' . $limit . ')',
            'search_query' => $query,
            'limit'        => $limit,
            'sort'         => $sort,
        ]);
    }

    /**
     * API: поиск по названию датасета
     */
    public function search(Request $request)
    {
        $query = $request->query('q', '');

        if (strlen($query) < 2) {
            return view('osdr', ['items' => [], 'search_query' => $query]);
        }

        $results = $this->osdrService->search($query);
        
        $flatItems = $results->map(fn($item) => [
            'id'          => $item->id,
            'dataset_id'  => $item->dataset_id,
            'title'       => $item->title,
            'rest_url'    => $item->getRestUrl(),
            'raw'         => is_array($item->raw) ? $item->raw : json_decode($item->raw, true),
        ])->toArray();

        return view('osdr', [
            'items' => $flatItems,
            'search_query' => $query,
            'src' => 'Service: OsdrService::search("' . $query . '")',
        ]);
    }
}
