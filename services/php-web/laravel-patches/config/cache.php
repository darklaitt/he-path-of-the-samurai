<?php

return [
    'default' => env('CACHE_DRIVER', 'file'),
    
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => __DIR__.'/../storage/framework/cache/data',
        ],
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
    ],
    
    'prefix' => env('CACHE_PREFIX', 'laravel_cache'),
];
