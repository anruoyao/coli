<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\AppVersion;
use App\Settings\AppSettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 危险版本下线（最低安全版本）中间件。
 *
 * 客户端每个请求携带 `X-App-Version` + `X-App-Platform`，服务端比对后台配置的
 * 「最低安全版本」：低于该线 → 返回 426 + X-Require-Update + 最新版本信息，
 * App 据此进入强制更新页。不依赖启动检测，运行中的旧版本每次交互都会被拦截。
 *
 * 版本号可被客户端篡改，本中间件是服务端策略，用于拦截普通用户与脚本，
 * 非绝对安全边界。
 */
class CheckAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        // 白名单：版本检测接口必须可用，供 App 获取最新版本信息与下载链接
        if ($request->is('api/system/version/check')) {
            return $next($request);
        }

        $platform = strtolower($request->header('X-App-Platform', 'android'));
        if (! in_array($platform, ['android', 'ios'])) {
            $platform = 'android';
        }

        $current = trim((string) $request->header('X-App-Version', ''));
        if ($current === '') {
            // 未上报版本号：无法判定，放行（避免影响无版本头的旧客户端/网页调用）
            return $next($request);
        }

        $min = trim(app(AppSettings::class)->{'min_supported_version_' . $platform} ?? '');
        if ($min === '' || ! preg_match('/^[0-9]+(\.[0-9]+){1,3}$/', $min)) {
            return $next($request);
        }

        if (version_compare($current, $min, '<')) {
            $latest = AppVersion::query()
                ->active()
                ->where('platform', $platform)
                ->get()
                ->reduce(function (?AppVersion $carry, AppVersion $version) {
                    if ($carry === null || version_compare($version->code, $carry->code, '>')) {
                        return $version;
                    }
                    return $carry;
                });

            return response()->json([
                'status' => 'error',
                'code' => 426,
                'message' => __('api/version.require_update'),
                'data' => [
                    'require_update' => true,
                    'min_supported_version' => $min,
                    'latest' => $latest?->code,
                    'download_url' => $latest?->download_url,
                    'notes' => $latest?->notes,
                ],
            ], 426, [
                'X-Require-Update' => '1',
                'X-Min-Version' => $min,
            ]);
        }

        return $next($request);
    }
}