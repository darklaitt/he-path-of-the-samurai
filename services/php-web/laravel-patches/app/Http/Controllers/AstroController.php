<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AstroController extends Controller
{
    public function events(Request $request)
    {
        // Простая валидация без validator facade
        $lat  = (float) ($request->query('lat', 55.7558));
        $lon  = (float) ($request->query('lon', 37.6176));
        $search = $request->query('search', null);
        // Используем годовой диапазон для большей вероятности найти события
        $from = $request->query('from_date', now('UTC')->toDateString());
        $to   = $request->query('to_date', now('UTC')->addMonths(12)->toDateString());

        // Проверка диапазонов
        $lat = max(-90, min(90, $lat));
        $lon = max(-180, min(180, $lon));

        // Кэширование на 1 час
        $cacheKey = "astro_events_{$lat}_{$lon}_{$from}_{$to}";
        
        try {
            $data = Cache::remember($cacheKey, 3600, function () use ($lat, $lon, $from, $to) {
                return $this->fetchAstronomyEvents($lat, $lon, $from, $to);
            });
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Cache error: ' . $e->getMessage()
            ], 500);
        }

        // Фильтрация по поиску
        if ($search && isset($data['data'])) {
            $data['data'] = array_filter($data['data'], function($event) use ($search) {
                $searchLower = mb_strtolower($search);
                return mb_stripos($event['type'] ?? '', $search) !== false ||
                       mb_stripos($event['description'] ?? '', $search) !== false;
            });
            $data['data'] = array_values($data['data']);
        }

        return response()->json($data);
    }

    private function fetchAstronomyEvents($lat, $lon, $from, $to)
    {
        $appId  = env('ASTRO_APP_ID', '');
        $secret = env('ASTRO_APP_SECRET', '');
        
        // Если нет ключей - сразу возвращаем mock-данные
        if ($appId === '' || $secret === '') {
            return $this->getMockAstronomyData($from, $to);
        }

        $auth = base64_encode($appId . ':' . $secret);
        
        // Объединяем события для Sun и Moon
        $allEvents = [];
        $moonPhases = [];
        $sunEvents = [];
        
        // Запрос событий для Sun (затмения)
        $sunData = $this->fetchBodyEvents($auth, 'sun', $lat, $lon, $from, $to);
        if ($sunData['ok']) {
            $allEvents = array_merge($allEvents, $sunData['events']);
        }
        
        // Запрос событий для Moon (затмения)
        $moonData = $this->fetchBodyEvents($auth, 'moon', $lat, $lon, $from, $to);
        if ($moonData['ok']) {
            $allEvents = array_merge($allEvents, $moonData['events']);
        }
        
        // Запрос позиций для фаз луны и восходов/закатов
        $positions = $this->fetchBodyPositions($auth, $lat, $lon, $from, $to);
        if ($positions['ok']) {
            $moonPhases = $positions['moon_phases'];
            $sunEvents = $positions['sun_events'];
        }
        
        // Если все запросы неудачны - возвращаем mock
        if (!$sunData['ok'] && !$moonData['ok'] && !$positions['ok']) {
            return $this->getMockAstronomyData($from, $to);
        }

        return [
            'ok' => true,
            'data' => $allEvents,
            'moon' => $moonPhases,
            'sun' => $sunEvents,
        ];
    }

    private function fetchBodyEvents($auth, $body, $lat, $lon, $from, $to)
    {
        // Корректный endpoint согласно документации
        $url = "https://api.astronomyapi.com/api/v2/bodies/events/{$body}?" . http_build_query([
            'latitude'   => $lat,
            'longitude'  => $lon,
            'elevation'  => 0,  // Обязательный параметр
            'from_date'  => $from,
            'to_date'    => $to,
            'time'       => '00:00:00',  // Обязательный параметр
            'output'     => 'rows',      // Просим формат rows, где есть data.rows[].events[]
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . $auth,
            ],
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 0;
        $err  = curl_error($ch);
        curl_close($ch);

        error_log("AstronomyAPI {$body}: code={$code}, response_length=" . strlen($raw ?: ''));

        if ($raw === false || $code >= 400) {
            error_log("AstronomyAPI error for {$body}: code={$code}, error={$err}");
            return ['ok' => false, 'events' => [], 'moon_phases' => [], 'sun_events' => []];
        }

        $json = json_decode($raw, true);
        error_log("AstronomyAPI {$body} parsed: " . json_encode([
            'has_data' => isset($json['data']),
            'has_rows' => isset($json['data']['rows']),
            'has_table_rows' => isset($json['data']['table']['rows'])
        ]));
        
        // Парсинг событий из data.table.rows[].cells[] (реальный формат API)
        $events = [];
        $moonPhases = [];
        $sunEvents = [];
        
        // Формат rows: data.rows[].events[]
        if (isset($json['data']['rows']) && is_array($json['data']['rows'])) {
            foreach ($json['data']['rows'] as $row) {
                if (isset($row['events']) && is_array($row['events'])) {
                    foreach ($row['events'] as $event) {
                        $eventType = $event['type'] ?? 'unknown';
                        
                        // Обработка разных типов событий
                        if (strpos($eventType, 'eclipse') !== false) {
                            // Затмение
                            $events[] = [
                                'date' => isset($event['eventHighlights']['peak']['date']) 
                                    ? date('Y-m-d', strtotime($event['eventHighlights']['peak']['date'])) 
                                    : $from,
                                'time' => isset($event['eventHighlights']['peak']['date']) 
                                    ? date('H:i:s', strtotime($event['eventHighlights']['peak']['date'])) 
                                    : '00:00:00',
                                'type' => ucfirst(str_replace('_', ' ', $eventType)),
                                'description' => 'Obscuration: ' . ($event['extraInfo']['obscuration'] ?? 'N/A'),
                            ];
                        } elseif (isset($event['rise']) && $body === 'sun') {
                            // Восход/закат Солнца
                            $sunEvents[] = [
                                'type' => 'Sunrise',
                                'time' => date('H:i:s', strtotime($event['rise'])),
                                'date' => date('Y-m-d', strtotime($event['rise'])),
                            ];
                            if (isset($event['set'])) {
                                $sunEvents[] = [
                                    'type' => 'Sunset',
                                    'time' => date('H:i:s', strtotime($event['set'])),
                                    'date' => date('Y-m-d', strtotime($event['set'])),
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Формат table: data.table.rows[].cells[] — поддерживаем для совместимости
        elseif (isset($json['data']['table']['rows']) && is_array($json['data']['table']['rows'])) {
            foreach ($json['data']['table']['rows'] as $row) {
                if (isset($row['cells']) && is_array($row['cells'])) {
                    foreach ($row['cells'] as $event) {
                        $eventType = $event['type'] ?? 'unknown';
                        if (strpos($eventType, 'eclipse') !== false) {
                            $events[] = [
                                'date' => isset($event['eventHighlights']['peak']['date'])
                                    ? date('Y-m-d', strtotime($event['eventHighlights']['peak']['date']))
                                    : $from,
                                'time' => isset($event['eventHighlights']['peak']['date'])
                                    ? date('H:i:s', strtotime($event['eventHighlights']['peak']['date']))
                                    : '00:00:00',
                                'type' => ucfirst(str_replace('_', ' ', $eventType)),
                                'description' => 'Obscuration: ' . ($event['extraInfo']['obscuration'] ?? 'N/A'),
                            ];
                        } elseif (isset($event['rise']) && $body === 'sun') {
                            $sunEvents[] = [
                                'type' => 'Sunrise',
                                'time' => date('H:i:s', strtotime($event['rise'])),
                                'date' => date('Y-m-d', strtotime($event['rise'])),
                            ];
                            if (isset($event['set'])) {
                                $sunEvents[] = [
                                    'type' => 'Sunset',
                                    'time' => date('H:i:s', strtotime($event['set'])),
                                    'date' => date('Y-m-d', strtotime($event['set'])),
                                ];
                            }
                        }
                    }
                }
            }
        }

        return [
            'ok' => true,
            'events' => $events,
            'moon_phases' => $moonPhases,
            'sun_events' => $sunEvents,
        ];
    }

    private function fetchBodyPositions($auth, $lat, $lon, $from, $to)
    {
        // Ограничиваем период до 10 дней для оптимизации
        $fromDate = new \DateTime($from);
        $toDate = new \DateTime($to);
        $diff = $fromDate->diff($toDate)->days;
        if ($diff > 10) {
            $toDate = clone $fromDate;
            $toDate->modify('+10 days');
            $to = $toDate->format('Y-m-d');
        }

        $moonPhases = [];
        $sunEvents = [];

        // Получаем позиции луны
        $moonUrl = "https://api.astronomyapi.com/api/v2/bodies/positions/moon?" . http_build_query([
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => 0,
            'from_date' => $from,
            'to_date' => $to,
            'time' => '12:00:00',
            'output' => 'rows',
        ]);

        $ch = curl_init($moonUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $auth],
            CURLOPT_TIMEOUT => 15,
        ]);
        $moonRaw = curl_exec($ch);
        $moonCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($moonCode === 200 && $moonRaw) {
            $moonJson = json_decode($moonRaw, true);
            // Формат rows: data.rows[].positions[]
            if (isset($moonJson['data']['rows']) && is_array($moonJson['data']['rows'])) {
                foreach ($moonJson['data']['rows'] as $row) {
                    $rowDate = $row['date'] ?? null;
                    if (isset($row['positions']) && is_array($row['positions'])) {
                        foreach ($row['positions'] as $pos) {
                            $phaseStr = $pos['extraInfo']['phase']['string'] ?? null;
                            $phaseDate = $pos['date'] ?? $rowDate;
                            if ($phaseStr && $phaseDate) {
                                $moonPhases[] = [
                                    'phase' => $phaseStr,
                                    'date' => date('Y-m-d', strtotime($phaseDate)),
                                    'time' => date('H:i:s', strtotime($phaseDate)),
                                ];
                            }
                        }
                    }
                }
            }
            // Формат table: data.table.rows[].cells[] — fallback
            elseif (isset($moonJson['data']['table']['rows'])) {
                foreach ($moonJson['data']['table']['rows'] as $row) {
                    if (isset($row['cells'])) {
                        foreach ($row['cells'] as $cell) {
                            $phaseStr = $cell['extraInfo']['phase']['string'] ?? null;
                            $phaseDate = $cell['date'] ?? ($cell['eventHighlights']['peak']['date'] ?? null);
                            if ($phaseStr && $phaseDate) {
                                $moonPhases[] = [
                                    'phase' => $phaseStr,
                                    'date' => date('Y-m-d', strtotime($phaseDate)),
                                    'time' => date('H:i:s', strtotime($phaseDate)),
                                ];
                            }
                        }
                    }
                }
            }
        }

        // События Солнца не формируем из positions (реально недоступно в этом endpoint)

        return [
            'ok' => true,
            'moon_phases' => $moonPhases,
            'sun_events' => $sunEvents,
        ];
    }

    private function extractMoonPhases($json)
    {
        $phases = [];
        if (isset($json['data']['table']['rows'])) {
            foreach ($json['data']['table']['rows'] as $row) {
                $name = $row['cells'][0]['name'] ?? '';
                if (stripos($name, 'moon') !== false || stripos($name, 'lunar') !== false) {
                    $phases[] = [
                        'phase' => $name,
                        'date' => $row['cells'][0]['date'] ?? '—',
                    ];
                }
            }
        }
        return $phases;
    }

    private function extractSunEvents($json)
    {
        $events = [];
        if (isset($json['data']['table']['rows'])) {
            foreach ($json['data']['table']['rows'] as $row) {
                $name = $row['cells'][0]['name'] ?? '';
                if (stripos($name, 'sun') !== false || stripos($name, 'rise') !== false || stripos($name, 'set') !== false) {
                    $events[] = [
                        'type' => $name,
                        'time' => $row['cells'][0]['time'] ?? '—',
                    ];
                }
            }
        }
        return $events;
    }

    /**
     * Возвращает демонстрационные данные при недоступности API
     */
    private function getMockAstronomyData($from, $to)
    {
        $fromDate = new \DateTime($from);
        $toDate = new \DateTime($to);
        $events = [];
        $moonPhases = [];
        $sunEvents = [];

        // Генерируем события для каждого дня
        $current = clone $fromDate;
        while ($current <= $toDate) {
            $dateStr = $current->format('Y-m-d');
            
            // Восход и закат Солнца (примерные для Москвы)
            $sunEvents[] = [
                'type' => 'Восход Солнца',
                'time' => '08:' . str_pad(rand(30, 59), 2, '0', STR_PAD_LEFT),
                'date' => $dateStr
            ];
            $sunEvents[] = [
                'type' => 'Закат Солнца',
                'time' => '16:' . str_pad(rand(0, 30), 2, '0', STR_PAD_LEFT),
                'date' => $dateStr
            ];

            // Случайные астрономические события
            if (rand(0, 2) === 0) {
                $eventTypes = [
                    ['type' => 'Меркурий максимальная элонгация', 'desc' => 'Планета Меркурий достигает максимального углового расстояния от Солнца'],
                    ['type' => 'Венера в соединении с Луной', 'desc' => 'Венера проходит близко к Луне'],
                    ['type' => 'Марс в оппозиции', 'desc' => 'Марс находится на противоположной стороне от Солнца'],
                    ['type' => 'Юпитер наибольший блеск', 'desc' => 'Юпитер достигает максимальной яркости'],
                    ['type' => 'Сатурн видимость', 'desc' => 'Оптимальные условия для наблюдения Сатурна'],
                    ['type' => 'ISS пролёт', 'desc' => 'Международная космическая станция видна невооружённым глазом'],
                ];
                $event = $eventTypes[array_rand($eventTypes)];
                $events[] = [
                    'date' => $dateStr,
                    'type' => $event['type'],
                    'time' => rand(18, 23) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT),
                    'description' => $event['desc']
                ];
            }

            $current->modify('+1 day');
        }

        // Фазы Луны (примерные даты)
        $moonPhaseTypes = ['Новолуние', 'Первая четверть', 'Полнолуние', 'Последняя четверть'];
        $phaseDate = clone $fromDate;
        for ($i = 0; $i < 2; $i++) {
            if ($phaseDate <= $toDate) {
                $moonPhases[] = [
                    'phase' => $moonPhaseTypes[array_rand($moonPhaseTypes)],
                    'date' => $phaseDate->format('Y-m-d'),
                    'time' => rand(0, 23) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT)
                ];
                $phaseDate->modify('+3 days');
            }
        }

        return [
            'ok' => true,
            'data' => $events,
            'moon' => $moonPhases,
            'sun' => $sunEvents,
            'mock' => true,
            'message' => 'Демонстрационные данные (API недоступен)'
        ];
    }

    /**
     * Получить позиции всех небесных тел
     * GET /api/astro/positions?latitude=X&longitude=Y&elevation=Z&from_date=...&to_date=...&time=HH:MM:SS
     */
    public function positions(Request $request)
    {
        $lat = $request->query('latitude', 55.7558);
        $lon = $request->query('longitude', 37.6176);
        $elevation = $request->query('elevation', 0);
        $from = $request->query('from_date', now('UTC')->toDateString());
        $to = $request->query('to_date', now('UTC')->toDateString());
        $time = $request->query('time', '12:00:00');

        $appId = env('ASTRO_APP_ID', '');
        $secret = env('ASTRO_APP_SECRET', '');

        if (empty($appId) || empty($secret)) {
            return response()->json(['ok' => false, 'error' => 'API credentials not configured'], 500);
        }

        $auth = base64_encode($appId . ':' . $secret);
        $url = "https://api.astronomyapi.com/api/v2/bodies/positions?" . http_build_query([
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => $elevation,
            'from_date' => $from,
            'to_date' => $to,
            'time' => $time,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ["Authorization: Basic {$auth}"],
        ]);

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$raw) {
            return response()->json(['ok' => false, 'error' => 'API request failed', 'code' => $code], 500);
        }

        $data = json_decode($raw, true);
        return response()->json(['ok' => true, 'data' => $data['data'] ?? []]);
    }

    /**
     * Получить позиции конкретного небесного тела
     * GET /api/astro/positions/{body}?latitude=X&longitude=Y&elevation=Z&from_date=...&to_date=...&time=HH:MM:SS
     */
    public function bodyPositions(Request $request, $body)
    {
        $lat = $request->query('latitude', 55.7558);
        $lon = $request->query('longitude', 37.6176);
        $elevation = $request->query('elevation', 0);
        $from = $request->query('from_date', now('UTC')->toDateString());
        $to = $request->query('to_date', now('UTC')->toDateString());
        $time = $request->query('time', '12:00:00');

        $appId = env('ASTRO_APP_ID', '');
        $secret = env('ASTRO_APP_SECRET', '');

        if (empty($appId) || empty($secret)) {
            return response()->json(['ok' => false, 'error' => 'API credentials not configured'], 500);
        }

        $auth = base64_encode($appId . ':' . $secret);
        $url = "https://api.astronomyapi.com/api/v2/bodies/positions/{$body}?" . http_build_query([
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => $elevation,
            'from_date' => $from,
            'to_date' => $to,
            'time' => $time,
            'output' => 'rows',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ["Authorization: Basic {$auth}"],
        ]);

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$raw) {
            return response()->json(['ok' => false, 'error' => 'API request failed', 'code' => $code], 500);
        }

        $data = json_decode($raw, true);
        return response()->json(['ok' => true, 'data' => $data['data'] ?? []]);
    }
}

