<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Ultimate Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        using: function() {
            Route::prefix(config('app.admin_prefix'))->middleware('admin-area')->group(base_path('routes/admin/web.php'));
            Route::middleware(['web', 'restrict.ip', 'device.identifier', 'terminator'])->group(base_path('routes/downloads.php'));
            Route::middleware(['web', 'restrict.ip', 'device.identifier', 'terminator'])->group(base_path('routes/social.php'));
            Route::middleware(['web', 'restrict.ip', 'device.identifier', 'terminator'])->group(base_path('routes/document.php'));
            Route::middleware(['web', 'restrict.ip', 'auth', 'user.status', 'device.identifier', 'terminator'])->prefix('business')->group(base_path('routes/business.php'));
            Route::middleware(['api', 'app.key', 'maintenance', 'app.version', 'log.request', 'restrict.ip', 'device.identifier', 'terminator', 'user.status'])->prefix('api')->group(base_path('routes/api.php'));
            Route::withoutMiddleware()->group(base_path('routes/webhooks/payment_webhooks.php'));
            Route::withoutMiddleware()->group(base_path('routes/callbacks.php'));

            Route::middleware(['web', 'maintenance', 'restrict.ip', 'device.identifier', 'terminator'])->group(base_path('routes/web.php'));

        })->withMiddleware(function (Middleware $middleware) {

            $middleware->redirectGuestsTo('auth/login');

            // 维护模式：把 CheckMaintenance 加入中间件优先级，令其先于 Authenticate 执行，
            // 保证未登录的页面/API 请求在维护时返回 503 维护响应而非被 401/302（Authenticate 优先）。
            // 仅作用于同时挂载了维护中间件的路由（前端 api/web 组），后台与 Livewire 不涉及。
            $middleware->prependToPriorityList(
                \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
                \App\Http\Middleware\CheckMaintenance::class,
            );

            $middleware->alias([
                'user.status' => App\Http\Middleware\UserStatusMiddleware::class,
                'maintenance' => App\Http\Middleware\CheckMaintenance::class,
                'app.version' => App\Http\Middleware\CheckAppVersion::class,
                'device.identifier' => App\Http\Middleware\DeviceIdentifierMiddleware::class,
                'terminator' => App\Http\Middleware\TerminatingMiddleware::class,
                'restrict.ip' => App\Http\Middleware\RestrictIPAddressMiddleware::class,
                'features.status' => App\Http\Middleware\FeatureStatusMiddleware::class,
                'sided.layout' => App\Http\Middleware\SidedLayoutMiddleware::class,
                'api.key' => App\Http\Middleware\VerifyApiKey::class,
                'admin' => App\Http\Middleware\AdminRoleMiddleware::class,
                'log.request' => App\Http\Middleware\LogRequestMiddleware::class,
                'abuse.guard' => App\Http\Middleware\AbuseGuardMiddleware::class,
                'app.key' => App\Http\Middleware\VerifyAppKey::class,
            ]);

            $middleware->web(append: [
                App\Http\Middleware\UserLanguageMiddleware::class,
                App\Http\Middleware\UserOnlineMiddleware::class
            ]);

            $middleware->api(append: [
                App\Http\Middleware\UserLanguageMiddleware::class,
                App\Http\Middleware\UserOnlineMiddleware::class
            ]);

            $middleware->statefulApi();

            $middleware->trustProxies('*');

            $middleware->group('admin-area', ['web', 'admin']);

        })->withExceptions(function (Exceptions $exceptions) {

        })->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
            // 聚合 Digest 邮件调度（错峰派发，cron 每分钟触发 schedule:run）
            $schedule->command('notification:send-digest')->everyMinute()->withoutOverlapping();
        })->create();
