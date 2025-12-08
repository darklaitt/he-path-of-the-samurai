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
        $limit = min((int)$request->query('limit', 20), 100);
        
        // Получаем OsdrItemDTO через сервис с кэшированием и фильтрацией
        $items = $this->osdrService->getList($limit);
        
        // Преобразуем DTO в массив для обратной совместимости с Blade шаблонами
        $flatItems = $items->map(fn($item) => [
            'id'          => $item->id,
            'dataset_id'  => $item->dataset_id,
            'title'       => $item->dataset_title,
            'status'      => 'active',
            'updated_at'  => null,
            'inserted_at' => null,
            'rest_url'    => $item->getRawRestUrl(),
            'raw'         => $item->variables ?? [],
        ])->toArray();

        return view('osdr', [
            'items' => $flatItems,
            'src'   => 'Service: OsdrService::getList(' . $limit . ')',
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
            'title'       => $item->dataset_title,
            'rest_url'    => $item->getRawRestUrl(),
            'raw'         => $item->variables ?? [],
        ])->toArray();

        return view('osdr', [
            'items' => $flatItems,
            'search_query' => $query,
            'src' => 'Service: OsdrService::search("' . $query . '")',
        ]);
    }
}
