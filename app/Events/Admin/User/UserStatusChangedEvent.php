<?php

namespace App\Events\Admin\User;

use App\Enums\User\UserStatus;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 用户账号状态变更广播（封禁/停用/解封）。
 *
 * 推送到用户私有频道 `private-App.Models.User.{id}`，事件名 `main.command`。
 * App 端收到后跳转封禁页或恢复（推的通道；拉兜底由 UserStatusMiddleware + 401 处理完成）。
 */
class UserStatusChangedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;

    public UserStatus $status;

    public ?string $reason;

    public function __construct(User $user, UserStatus $status, ?string $reason = null)
    {
        $this->user = $user;
        $this->status = $status;
        $this->reason = $reason;
    }

    public function broadcastAs(): string
    {
        return 'main.command';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.User.{$this->user->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->status->value, // banned / suspended / active（解封）
            'reason' => $this->reason,
            'ts' => now()->toIso8601String(),
        ];
    }
}