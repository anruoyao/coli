<?php

namespace App\Http\Middleware;

use Closure;
use App\Settings\MaintenanceSettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 全局维护（应急）模式中间件。
 *
 * 开启后：所有 API 请求（除白名单，如版本检测）返回 503 + X-Maintenance 头，
 * App 端据此进入维护提示页；拉兜底 + 后台可正常访问以关闭维护。
 */
class CheckMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(MaintenanceSettings::class);

        if (! $settings->enabled) {
            return $next($request);
        }

        // 白名单：维护期间保持可用 —— 后台（用于关闭维护）、文件下载（配合 App 强制更新）、
        // 版本检测（App 启动时据此区分「维护」与「无更新」，避免误判）。
        // 注意 Laravel 中间件优先级会让 Authenticate 提前执行，导致未登录的页面请求在
        // 本中间件之前被 302 到登录页，因此本中间件以「全局前置（before）」方式挂载。
        $adminPrefix = trim((string) config('app.admin_prefix'), '/');
        $isWhitelisted = ($adminPrefix !== '' && $request->is($adminPrefix, $adminPrefix.'/*'))
            || $request->is('file-downloads/*')
            || $request->is('api/system/version/check');

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

        // 网页端：渲染维护公告页（503），后台与下载链接不在本中间件内，不受影响
        $until = ! empty($settings->until) ? \Illuminate\Support\Carbon::parse($settings->until)->format('Y-m-d H:i') : null;

        return response()->view('errors.maintenance', [
            'message' => $settings->message ?: '',
            'until' => $until,
        ], 503);
    }
}