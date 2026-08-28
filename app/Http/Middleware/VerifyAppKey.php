<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 客户端请求密钥校验（X-App-Key）。
 *
 * 定位：准入门槛 + 击杀开关，非安全边界——
 * 密钥必然存在于 APK / 网页 bundle 中，可用抓包/反编译提取，因此它不承载
 * 机密语义，仅是「官方客户端标识」。但足以把「拿 API 文档 + Postman 直接打」
 * 的非官方脚本拒之门外，并支持密钥轮换（泄露即从白名单移除，全体立即失效）。
 *
 * 规则：
 * - SECURITY_APP_KEY_ENABLED=false → 放行（新发布灰度兼容旧客户端）
 * - 白名单为空 → 放行（视为未配置环境）
 * - 未携带或不在白名单 → 返回 404（伪装不存在，避免探测）
 *
 * 挂在 /api 前缀组最前（先于 maintenance/auth 等一切逻辑）。
 */
class VerifyAppKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = config('security.app_key');

        if (empty($settings['enabled'])) {
            return $next($request);
        }

        $keys = $settings['keys'] ?? [];

        if (empty($keys)) {
            return $next($request);
        }

        $key = (string) $request->header('X-App-Key', '');

        if ($key === '' || ! in_array($key, $keys, true)) {
            return response()->json([
                'status'  => 'error',
                'code'    => 404,
                'message' => 'Not Found.',
            ], 404);
        }

        return $next($request);
    }
}