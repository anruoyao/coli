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
            Route::middleware(['api', 'app.key', 'global.ip.gate', 'maintenance', 'app.version', 'log.request', 'restrict.ip', 'device.identifier', 'terminator', 'user.status'])->prefix('api')->group(base_path('routes/api.php'));
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
                'global.ip.gate' => App\Http\Middleware\GlobalIpGateMiddleware::class,
                'app.key' => App\Http\Middleware\VerifyAppKey::class,
                'guest.enabled' => App\Http\Middleware\Guest\EnsureGuestEnabled::class,
                'guest.context' => App\Http\Middleware\Guest\GuestContextMiddleware::class,
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

            // API 限流 429 统一渲染：与 AbuseGuard / GlobalIpGate 的响应结构对齐
            // {status:'error', code:429, message} + 透传 Retry-After / X-RateLimit-* 头。
            // 注意：框架 throttle 抛出的 TooManyRequestsHttpException 自带这些头，
            // 但 render 回调返回的响应不会自动合并异常 headers，必须显式 withHeaders。
            $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, \Illuminate\Http\Request $request) {
                if (! $request->is('api/*')) {
                    return null; // 非 API 请求走框架默认渲染
                }

                $headers = $e->getHeaders();
                $limit = isset($headers['X-RateLimit-Limit']) ? (int) $headers['X-RateLimit-Limit'] : null;

                // 从路径提取限流分类（api/timeline/feed → throttle:timeline）
                $category = preg_match('#^api/([^/]+)#', $request->path(), $matches)
                    ? 'throttle:' . $matches[1]
                    : 'throttle:unknown';

                // 限流维度：登录用户记 user 维度，未登录记 IP 维度
                $user = $request->user() ?: $request->user('sanctum');
                if ($user) {
                    record_throttle_event(\App\Models\ApiThrottleEvent::DIMENSION_USER, (string) $user->id, $category, null, $limit);
                } else {
                    record_throttle_event(\App\Models\ApiThrottleEvent::DIMENSION_IP, (string) $request->ip(), $category, null, $limit);
                }

                return response()->json([
                    'status'  => 'error',
                    'code'    => 429,
                    'message' => __('api/error.throttle'),
                ], 429)->withHeaders($headers);
            });

        })->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
            // 聚合 Digest 邮件调度（错峰派发，cron 每分钟触发 schedule:run）
            $schedule->command('notification:send-digest')->everyMinute()->withoutOverlapping();
            // 营销活动分片派发（邮件受智能限速约束，每分钟错峰派发）
            $schedule->command('marketing:send')->everyMinute()->withoutOverlapping();
            // API 限流事件裁剪（保留 security.throttle_events.retention_days 天）
            $schedule->command('colibri:prune-throttle-events')->dailyAt('03:00');
        })->create();
