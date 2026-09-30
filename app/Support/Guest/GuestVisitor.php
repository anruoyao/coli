<?php

namespace App\Support\Guest;

/**
 * 访客匿名上下文（只读值对象）。
 *
 * 访客身份不对应任何用户记录：以 DeviceIdentifierMiddleware 自动下发的
 * device_id Cookie 为主标识，IP 为回退，用于限流计数与请求日志。
 */
class GuestVisitor
{
    public function __construct(
        public readonly ?string $deviceId,
        public readonly ?string $ip,
    ) {
    }

    /**
     * 限流/统计维度的稳定 key：device_id 优先，回退 IP，最终兜底 unknown。
     */
    public function key(): string
    {
        return $this->deviceId ?: ($this->ip ?: 'unknown');
    }
}
