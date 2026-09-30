<?php

namespace App\Http\Middleware\Guest;

use App\Settings\GuestSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 访客功能总开关校验。
 *
 * GuestSettings::enabled=false 时，全部访客 API 返回 403（前端应引导登录）；
 * 挂在访客路由组最前，先于任何内容查询。
 */
class EnsureGuestEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        // 已登录用户（session 态）不受访客开关影响——前端 boot 统一走访客 bootstrap，
        // 若开关关闭时把登录用户也挡下会导致其误进 bootstrap-error。
        if ($request->user() !== null) {
            return $next($request);
        }

        if (! app(GuestSettings::class)->enabled) {
            return response()->json([
                'status'  => 'error',
                'code'    => 403,
                'message' => 'Guest access is disabled.',
            ], 403);
        }

        return $next($request);
    }
}
