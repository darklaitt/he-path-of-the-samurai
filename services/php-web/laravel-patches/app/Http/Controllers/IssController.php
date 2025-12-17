<?php

namespace App\Http\Controllers;

class IssController extends Controller
{
    public function index()
    {
        $base = env('RUST_ISS_BASE', 'http://rust_iss:3000');

        $last  = @file_get_contents($base.'/last');
        $trend = @file_get_contents($base.'/iss/trend');

        $lastJson  = $last  ? json_decode($last,  true) : [];
        $trendJson = $trend ? json_decode($trend, true) : [];

        // Извлекаем data из ответа Rust API
        if (!empty($lastJson['data'])) {
            $lastJson = $lastJson['data'];
        }
        if (!empty($trendJson['data'])) {
            $trendJson = $trendJson['data'];
        }

        return view('iss', ['last' => $lastJson, 'trend' => $trendJson, 'base' => $base]);
    }
}
