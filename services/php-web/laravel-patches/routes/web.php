<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OsdrController;
use App\Http\Controllers\IssController;
use App\Http\Controllers\ProxyController;
use App\Http\Controllers\AstroController;
use App\Http\Controllers\CmsController;
use App\Http\Controllers\UploadController;

Route::get('/', fn () => redirect('/dashboard'));

// ===== Основные панели по контекстам =====
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');   // сводный дашборд
Route::get('/iss',       [IssController::class,       'index'])->name('iss');        // контекст ISS
Route::get('/osdr',      [OsdrController::class,      'index'])->name('osdr');       // контекст OSDR
Route::get('/jwst',      fn () => view('jwst'))->name('jwst');                       // контекст JWST
Route::get('/astronomy', fn () => view('astronomy'))->name('astronomy');             // контекст Astronomy
Route::get('/astro',     fn () => view('dashboard'))->name('astro'); // AstronomyAPI на дашборде

// ===== API-прокси к rust_iss и внешним сервисам =====
Route::get('/api/iss/last',  [ProxyController::class, 'last']);
Route::get('/api/iss/trend', [ProxyController::class, 'trend']);

// JWST галерея (JSON)
Route::get('/api/jwst/feed', [DashboardController::class, 'jwstFeed']);

// AstronomyAPI события и позиции
Route::get('/api/astro/events', [AstroController::class, 'events']);
Route::get('/api/astro/positions', [AstroController::class, 'positions']);
Route::get('/api/astro/positions/{body}', [AstroController::class, 'bodyPositions']);

// CMS-страницы
Route::get('/page/{slug}', [CmsController::class, 'page'])->name('cms.page');

// Загрузка файлов (legacy, можно скрыть за флагом)
Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');

