<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * DashboardController - главный дашборд
 */
class DashboardController extends Controller
{
    public function index()
    {
        // Получаем данные из Rust API
        $base = env('RUST_ISS_BASE', 'http://rust_iss:3000');
        
        // ISS данные
        try {
            $ch = curl_init($base . '/last');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $raw = curl_exec($ch);
            $lastData = $raw ? json_decode($raw, true) : null;
            curl_close($ch);

            $iss = isset($lastData['data']) ? $lastData['data'] : [];
        } catch (\Exception $e) {
            $iss = [];
        }

        // OSDR данные
        try {
            $ch = curl_init($base . '/osdr/list?limit=5');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $raw = curl_exec($ch);
            $osdrData = $raw ? json_decode($raw, true) : null;
            curl_close($ch);

            $osdr = isset($osdrData['data']['items']) ? $osdrData['data']['items'] : [];
        } catch (\Exception $e) {
            $osdr = [];
        }

        // APOD данные
        try {
            $ch = curl_init($base . '/space/latest/apod');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $raw = curl_exec($ch);
            $apodData = $raw ? json_decode($raw, true) : null;
            curl_close($ch);

            $apod = isset($apodData['data']) ? $apodData['data'] : null;
        } catch (\Exception $e) {
            $apod = null;
        }

        // NEO данные
        try {
            $today = date('Y-m-d');
            $ch = curl_init($base . '/space/latest/neo');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $raw = curl_exec($ch);
            $neoData = $raw ? json_decode($raw, true) : null;
            curl_close($ch);

            $neo = isset($neoData['data']['element_count']) ? $neoData['data']['element_count'] : 0;
        } catch (\Exception $e) {
            $neo = 0;
        }

        return view('dashboard', [
            'iss' => $iss,
            'osdr' => $osdr,
            'apod' => $apod,
            'neo' => $neo,
            'base' => $base,
        ]);
    }

    /**
     * /api/jwst/feed — серверный прокси/нормализатор JWST картинок.
     * QS:
     *  - source: jpg|suffix|program (default jpg)
     *  - suffix: напр. _cal, _thumb, _crf
     *  - program: ID программы (число)
     *  - instrument: NIRCam|MIRI|NIRISS|NIRSpec|FGS
     *  - page, perPage
     */
    public function jwstFeed(Request $r)
    {
        $src   = $r->query('source', 'jpg');
        $sfx   = trim((string)$r->query('suffix', ''));
        $prog  = trim((string)$r->query('program', ''));
        $instF = strtoupper(trim((string)$r->query('instrument', '')));
        $page  = max(1, (int)$r->query('page', 1));
        $per   = max(1, min(60, (int)$r->query('perPage', 24)));

        $jw = new JwstHelper();

        // выбираем эндпоинт
        $path = 'all/type/jpg';
        if ($src === 'suffix' && $sfx !== '') $path = 'all/suffix/'.ltrim($sfx,'/');
        if ($src === 'program' && $prog !== '') $path = 'program/id/'.rawurlencode($prog);

        $resp = $jw->get($path, ['page'=>$page, 'perPage'=>$per]);
        $list = $resp['body'] ?? ($resp['data'] ?? (is_array($resp) ? $resp : []));

        $items = [];
        foreach ($list as $it) {
            if (!is_array($it)) continue;

            // выбираем валидную картинку
            $url = null;
            $loc = $it['location'] ?? $it['url'] ?? null;
            $thumb = $it['thumbnail'] ?? null;
            foreach ([$loc, $thumb] as $u) {
                if (is_string($u) && preg_match('~\.(jpg|jpeg|png)(\?.*)?$~i', $u)) { $url = $u; break; }
            }
            if (!$url) {
                $url = \App\Support\JwstHelper::pickImageUrl($it);
            }
            if (!$url) continue;

            // фильтр по инструменту
            $instList = [];
            foreach (($it['details']['instruments'] ?? []) as $I) {
                if (is_array($I) && !empty($I['instrument'])) $instList[] = strtoupper($I['instrument']);
            }
            if ($instF && $instList && !in_array($instF, $instList, true)) continue;

            $items[] = [
                'url'      => $url,
                'obs'      => (string)($it['observation_id'] ?? $it['observationId'] ?? ''),
                'program'  => (string)($it['program'] ?? ''),
                'suffix'   => (string)($it['details']['suffix'] ?? $it['suffix'] ?? ''),
                'inst'     => $instList,
                'caption'  => trim(
                    (($it['observation_id'] ?? '') ?: ($it['id'] ?? '')) .
                    ' · P' . ($it['program'] ?? '-') .
                    (($it['details']['suffix'] ?? '') ? ' · ' . $it['details']['suffix'] : '') .
                    ($instList ? ' · ' . implode('/', $instList) : '')
                ),
                'link'     => $loc ?: $url,
            ];
            if (count($items) >= $per) break;
        }

        return response()->json([
            'source' => $path,
            'count'  => count($items),
            'items'  => $items,
        ]);
    }
}
