<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus App 在线心跳 API（P0 在线用户数）
|--------------------------------------------------------------------------
| 说明：由 Flutter App 在前/后台切换时调用，精确控制「在线/离席」：
|   - 前台（background=false）：标记在线，进入 Redis 在线集合；
|   - 后台（background=true）：即刻离席（ZREM），last_seen 冻结，窗口过期自动清除。
| 平台/客户端标识取自 X-App-Platform / X-Client-Id 头，App 每次请求均已携带。
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\Api\User\Presence;

use App\Http\Controllers\Controller;
use App\Services\User\PresenceService;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    use SupportsApiResponses;

    /**
     * POST /api/presence/heartbeat
     * body: { background?: bool }
     */
    public function heartbeat(Request $request)
    {
        $background = (bool) $request->input('background', false);

        app(PresenceService::class)->touch(me(), [
            'client_id'        => (string) $request->header('X-Client-Id', ''),
            'platform'         => $this->resolvePlatform($request),
            'platform_detail'  => $this->resolvePlatformDetail($request),
            'is_background'    => $background,
            'ip_address'       => $request->ip(),
        ]);

        return $this->responseSuccess([
            'data' => [
                'background' => $background,
                'online'     => ! $background,
                'window_min' => (int) config('user.online_interval_in_minutes', 5),
            ],
        ], 200);
    }

    private function resolvePlatform(Request $request): string
    {
        $platform = strtolower((string) $request->header('X-App-Platform'));

        return in_array($platform, ['android', 'ios']) ? $platform : 'android';
    }

    private function resolvePlatformDetail(Request $request): string
    {
        $version = (string) $request->header('X-App-Version');

        return $version !== '' ? 'App ' . $version : 'App';
    }
}