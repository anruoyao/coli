<?php

namespace App\Providers;

use App\Data\DataCapsule;
use App\Support\Languages;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use App\Services\Filesystem\RoundRobin\RoundRobinService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RoundRobinService::class, function () {
            return new RoundRobinService();
        });

        $this->app->singleton(DataCapsule::class, function () {
            return new DataCapsule();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        bcscale(2);

        View::composer('*', function($view) {
            $view->with('localeName', (new Languages())->getLocaleName());
            $view->with('buildNumber', cache()->rememberForever('frontend_build_number', function() {
                return file_get_contents(storage_path('frontend/build.num')) ?? random_int(1, 1000000);
            }));
        });
        
        LogViewer::auth(function() {
            return auth_check() && me()->isAdmin();
        });
    }
}
