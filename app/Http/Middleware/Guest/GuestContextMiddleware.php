<?php

namespace App\Http\Middleware\Guest;

use App\Support\Guest\GuestVisitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 绑定访客匿名上下文。
 *
 * 从 device_id Cookie（缺省回退 IP）构造 GuestVisitor 并以单例绑定到容器，
 * 供控制器/日志读取；本中间件不写数据库。
 */
class GuestContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = trim((string) $request->cookie('device_id', ''));

        $visitor = new GuestVisitor(
            deviceId: $deviceId !== '' ? $deviceId : null,
            ip: $request->ip(),
        );

        app()->instance(GuestVisitor::class, $visitor);

        return $next($request);
    }
}
