<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\IssService;
use App\Services\OsdrService;
use App\Services\SpaceService;
use App\Repositories\IssRepository;
use App\Repositories\OsdrRepository;
use App\Repositories\SpaceRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрируем Repositories (singleton для переиспользования)
        $this->app->singleton(IssRepository::class);
        $this->app->singleton(OsdrRepository::class);
        $this->app->singleton(SpaceRepository::class);
        
        // Регистрируем Services (зависят от Repositories)
        $this->app->singleton(IssService::class, function ($app) {
            return new IssService(
                $app->make(IssRepository::class)
            );
        });

        $this->app->singleton(OsdrService::class, function ($app) {
            return new OsdrService(
                $app->make(OsdrRepository::class)
            );
        });

        $this->app->singleton(SpaceService::class, function ($app) {
            return new SpaceService(
                $app->make(SpaceRepository::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
