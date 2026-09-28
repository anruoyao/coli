<?php

namespace App\Listeners\User\Auth;

use Throwable;
use App\Events\User\Auth\UserLoggedInEvent;
use App\Actions\User\UpdateUserDeviceAction;
use Illuminate\Support\Facades\Log;

class HandleUserLogin
{
    /**
     * Handle the event.
     *
     * 设备档案采集是登录的附加动作：任何失败（如历史版本的
     * user_agent 超长、IP 库异常）都不应中断登录链路 ——
     * 否则 confirm-signup 直接 500，用户重试会重复建号
     * （2026-09-28 线上已实际发生过批量重复用户）。
     */
    public function handle(UserLoggedInEvent $event): void
    {
        $user = $event->user;

        try {
            (new UpdateUserDeviceAction())->execute($user);
        } catch (Throwable $e) {
            Log::error('Failed to record user device on login.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
