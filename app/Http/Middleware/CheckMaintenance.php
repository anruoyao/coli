<?php

namespace App\Http\Middleware;

use Closure;
use App\Settings\MaintenanceSettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 全局维护（应急）模式中间件。
 *
 * 只挂载在「前端 api 组」与「前端 web 组」上：
 * - 后台（admin-area 路由组）与 Livewire 端点（/livewire/update）天然不经过本中间件，
 *   因此**后台任何功能都不会被维护模式影响**，管理员随时可以关闭维护；
 * - 已登录的管理员（web session）也直接放行，双保险；
 * - 开启后：前端页面渲染维护公告页（503）；API 返回 503 + X-Maintenance 头，App 据此进维护页。
 */
class CheckMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        // 已登录管理员：维护期间任何请求放行（双保险，避免影响后台/管理操作）
        if (auth_check() && (me()->isAdmin() || me()->isRoot())) {
            return $next($request);
        }

        $settings = app(MaintenanceSettings::class);

        if (! $settings->enabled) {
            return $next($request);
        }

        // 白名单：后台登录入口（admin/login 位于前端 web.php 内，必须可登录才能进后台关维护）、
        // 文件下载（配合 App 强制更新）、版本检测（App 启动时区分「维护」与「无更新」）、
        // sitemap（维护期间搜索引擎仍可抓取已收录索引）。
        $adminPrefix = trim((string) config('app.admin_prefix'), '/');
        $isWhitelisted = ($adminPrefix !== '' && $request->is($adminPrefix.'/login'))
            || $request->is('file-downloads/*')
            || $request->is('api/system/version/check')
            || $request->is('sitemap.xml')
            || $request->is('sitemap-*');

        if ($isWhitelisted) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'code' => 503,
                'message' => $settings->message ?: __('api/error.maintenance'),
                'data' => [
                    'maintenance' => true,
                    'until' => $settings->until,
                ],
            ], 503, [
                'X-Maintenance' => '1',
            ]);
        }

        // 网页端：渲染维护公告页（503）
        $until = ! empty($settings->until) ? \Illuminate\Support\Carbon::parse($settings->until)->format('Y-m-d H:i') : null;

        return response()->view('errors.maintenance', [
            'message' => $settings->message ?: '',
            'until' => $until,
        ], 503);
    }
}