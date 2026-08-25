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

        // 白名单：版本检测等关键接口在维护期间必须可用，避免 App 将「维护」误判为「无更新」
        if ($request->is('api/system/version/check')) {
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
        return response()->view('errors.maintenance', [
            'message' => $settings->message ?: '',
            'until' => $settings->until?->format('Y-m-d H:i'),
        ], 503);
    }
}