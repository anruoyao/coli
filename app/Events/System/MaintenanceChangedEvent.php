<?php

namespace App\Events\System;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 全局维护模式变更广播（应急模式）。
 *
 * 推送到公共频道 `App.Commands`（无需鉴权），事件名 `main.command`。
 * App 端订阅该频道后收到 maintenance_on / maintenance_off，切换维护提示页。
 * 拉兜底由 CheckMaintenance 中间件（API 请求返回 X-Maintenance 头）完成。
 */
class MaintenanceChangedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $enabled;

    public ?string $message;

    public ?string $until;

    public function __construct(bool $enabled, ?string $message = null, ?string $until = null)
    {
        $this->enabled = $enabled;
        $this->message = $message;
        $this->until = $until;
    }

    public function broadcastAs(): string
    {
        return 'main.command';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('App.Commands'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->enabled ? 'maintenance_on' : 'maintenance_off',
            'message' => $this->message,
            'until' => $this->until,
            'ts' => now()->toIso8601String(),
        ];
    }
}