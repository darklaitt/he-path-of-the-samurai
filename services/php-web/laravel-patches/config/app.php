<?php

return [
    'name' => env('APP_NAME', 'Laravel'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => env('APP_KEY', 'base64:WkhxSjlpRzNrN1FyVUpRSjlpRzNrN1FyVUpRSjlpRzM='),
    'cipher' => 'AES-256-CBC',
    
    'providers' => [
        // Laravel Framework Service Providers
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,
        Illuminate\Routing\RoutingServiceProvider::class,
        
        // Application Service Providers
        App\Providers\AppServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ],
    
    'aliases' => [
        'DB' => Illuminate\Support\Facades\DB::class,
        'Cache' => Illuminate\Support\Facades\Cache::class,
    ],
];
