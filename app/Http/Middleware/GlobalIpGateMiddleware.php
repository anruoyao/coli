<?php

namespace App\Http\Middleware;

use App\Models\ApiThrottleEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * 全局 IP 闸门（L0 防线）：每 IP 全 API 总请求上限。
 *
 * 作用：组级 throttle（throttle:api.{category}）只限制单一路由组，攻击者在
 * 不同端点间轮换请求即可绕过；本闸门在 /api/* 入口处按 IP 汇总计数兜底。
 *
 * - 固定窗口计数（Cache add + increment，Redis 存储，与 AbuseGuard 同模式）
 * - 健康检查路径与白名单 IP 豁免
 * - 配置见 config/security.php 的 ip_gate
 * - 挂载位置：bootstrap/app.php 全局链 app.key 之后（所有 /api/* 请求均经过）
 */
class GlobalIpGateMiddleware
{
    private const CACHE_PREFIX = 'ipgate:';

    /** 健康检查/监控路径（相对路径，不含 /api 前缀亦可匹配） */
    private const EXEMPT_PATHS = [
        'up',
        'api/system/version/check',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.ip_gate.enabled', true)) {
            return $next($request);
        }

        $ip = $request->ip();

        // 白名单 IP 与健康检查路径豁免
        if (in_array($ip, (array) config('security.ip_gate.whitelist', []), true)
            || $request->is(...self::EXEMPT_PATHS)) {
            return $next($request);
        }

        $max = (int) config('security.ip_gate.max_per_minute', 600);
        $key = self::CACHE_PREFIX . $ip;

        $count = (int) Cache::get($key, 0);
        if ($count >= $max) {
            record_throttle_event(
                ApiThrottleEvent::DIMENSION_IP,
                (string) $ip,
                'global-ip-gate',
                null,
                $max,
                60
            );

            $retryAfter = 60;

            return response()->json([
                'status'  => 'error',
                'code'    => 429,
                'message' => __('api/error.throttle_seconds', ['seconds' => $retryAfter]),
            ], 429, [
                'Retry-After'         => $retryAfter,
                'X-RateLimit-Limit'   => $max,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset'   => now()->addSeconds($retryAfter)->getTimestamp(),
            ]);
        }

        // 确保 key 存在并带 TTL（已存在时 add 为空操作，不重置过期时间）
        Cache::add($key, 0, 60);
        Cache::increment($key);

        return $next($request);
    }
}
