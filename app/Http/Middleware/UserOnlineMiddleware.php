<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Social Network Web Application.
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

namespace App\Http\Middleware;

use Closure;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Services\User\PresenceService;
use App\Actions\User\UpdateUserDeviceAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 在线状态中间件（web + api 组均挂载）。
 *
 * 1. 用户级兜底：>= 在线窗口（默认 5 分钟）才写 users.last_active（既有 isOnline() 语义）；
 * 2. 会话级在线上报：presence_sessions 会话 upsert + Redis 计数（节流在 PresenceService 内）；
 * 3. 平台识别：App 取 X-App-Platform 头（web 由 UA 解析），客户端标识取 X-Client-Id（App UUID）/ 会话 id。
 */
class UserOnlineMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 中间件挂载于 web/api 组，执行时机在路由级 auth:sanctum 之前，
        // 因此 auth_check()（默认 web guard）对 API 请求恒为 false。
        // 显式兼容两套认证：web(session 默认 guard) + api(sanctum Bearer token)。
        $user = $request->user() ?: $request->user('sanctum');

        if ($user) {
            $platform = $this->resolvePlatform($request);

            $interval = (int) config('user.online_interval_in_minutes');
            if ($user->last_active < now()->subMinutes($interval)) {
                $user->last_active = now();
                $user->save();

                // 设备档案仅 web 端维护（App 平台明细由 presence_sessions 提供，避免污染 devices 表）
                if ($platform === 'web') {
                    try {
                        (new UpdateUserDeviceAction())->execute($user);
                    } catch (\Throwable $e) {
                        // 设备档案采集失败不阻塞在线上报
                    }
                }
            }

            try {
                $clientId = $request->header('X-Client-Id') ?: session()->getId();

                app(PresenceService::class)->touch($user, [
                    'client_id'        => (string) $clientId,
                    'platform'         => $platform,
                    'platform_detail'  => $this->resolvePlatformDetail($request, $platform),
                    'ip_address'       => $request->ip(),
                ]);
            } catch (\Throwable $e) {
                // 在线状态上报失败不应影响业务请求
            }
        }

        return $next($request);
    }

    private function resolvePlatform(Request $request): string
    {
        $appPlatform = strtolower((string) $request->header('X-App-Platform'));

        return in_array($appPlatform, ['android', 'ios']) ? $appPlatform : 'web';
    }

    private function resolvePlatformDetail(Request $request, string $platform): ?string
    {
        if ($platform !== 'web') {
            $version = (string) $request->header('X-App-Version');

            return $version !== '' ? 'App ' . $version : 'App';
        }

        try {
            $agent = new Agent();
            $agent->setUserAgent((string) $request->header('User-Agent'));

            $browser = trim($agent->browser() . ' ' . $agent->version($agent->browser()));
            $os      = trim($agent->platform() . ' ' . $agent->version($agent->platform()));

            return trim(rtrim($browser, '/') . ' / ' . $os);
        } catch (\Throwable $e) {
            return null;
        }
    }
}