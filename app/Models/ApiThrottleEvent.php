<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * API 限流事件（429 命中审计）。
 *
 * 由三处写入：
 * - bootstrap/app.php 的 TooManyRequestsHttpException 统一渲染（框架 throttle 命中）
 * - AbuseGuardMiddleware 拦截（风控动作限流命中）
 * - GlobalIpGateMiddleware 拦截（全局 IP 闸门命中）
 *
 * 写入路径均在外部请求生命周期内，单条失败不应阻断 429 响应返回。
 */
class ApiThrottleEvent extends Model
{
    /** 限流维度 */
    public const DIMENSION_USER = 'user';
    public const DIMENSION_IP = 'ip';
    public const DIMENSION_DEVICE = 'device';
    public const DIMENSION_GLOBAL = 'global';

    public $table = Table::API_THROTTLE_EVENTS;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'limit' => 'integer',
        'window_seconds' => 'integer',
    ];

    /**
     * 记录一次限流命中。请求上下文（path/method/ip/ua）内部取自当前 request，不信任参数。
     */
    public static function record(
        string $dimension,
        string $identifier,
        ?string $category = null,
        ?string $action = null,
        ?int $limit = null,
        ?int $windowSeconds = null
    ): self {
        $request = request();

        return static::create([
            'dimension' => $dimension,
            'identifier' => $identifier,
            'category' => $category,
            'action' => $action,
            'limit' => $limit,
            'window_seconds' => $windowSeconds,
            'path' => $request?->path(),
            'method' => $request?->method(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
