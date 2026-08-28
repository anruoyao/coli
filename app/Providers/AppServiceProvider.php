<?php

namespace App\Providers;

use App\Data\DataCapsule;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Support\Languages;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Opcodes\LogViewer\Facades\LogViewer;

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

        $this->registerAuthRateLimiters();

        View::composer('*', function($view) {
            $view->with('localeName', (new Languages())->getLocaleName());
            $view->with('buildNumber', cache()->rememberForever('frontend_build_number', function() {
                return file_get_contents(storage_path('frontend/build.num')) ?? random_int(1, 1000000);
            }));
        });

        LogViewer::auth(function() {
            return auth_check() && me()->isRoot();
        });

        $livewireComponents = [
            'admin.config.pwa' => \App\Livewire\Admin\Config\PWA::class,
            'admin.config.ffmpeg' => \App\Livewire\Admin\Config\FFMPeg::class,
            'admin.config.ffmpeg-test' => \App\Livewire\Admin\Config\FFMPegTest::class,
        ];

        foreach ($livewireComponents as $alias => $class) {
            Livewire::component($alias, $class);
        }
    }

    /**
     * 认证相关命名限流器（防御暴力登录 / 批量注册 / 邮箱轰炸）。
     * 供 routes/api.php 以 throttle:{name} 方式使用。
     */
    private function registerAuthRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinutes(5, (int) config('security.auth.login_max_attempts_per_ip', 10))->by($request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour((int) config('security.auth.register_max_per_ip', 10))->by($request->ip()));

        RateLimiter::for('forgot', fn (Request $request) => Limit::perHour((int) config('security.auth.forgot_max_per_ip', 5))->by($request->ip()));
    }
}
