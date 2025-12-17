<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Путь к "home" для вашего приложения.
     */
    public const HOME = '/dashboard';

    /**
     * Регистрация маршрутов приложения.
     */
    public function boot(): void
    {
        $this->routes(function () {
            Route::group([], base_path('routes/web.php'));
        });
    }
}
