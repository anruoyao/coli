<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App 版本记录（移动端版本管理）。
 *
 * 用于 colibri 后台配置移动应用版本，并通过公共接口
 * /api/system/version/check 供 Flutter App 启动时检测更新。
 */
class AppVersion extends Model
{
    public const PLATFORM = [
        'android' => 'Android',
        'ios' => 'iOS',
    ];

    protected $fillable = [
        'code',
        'platform',
        'download_url',
        'notes',
        'is_forced',
        'is_active',
        'released_at',
    ];

    protected $casts = [
        'is_forced' => 'boolean',
        'is_active' => 'boolean',
        'released_at' => 'datetime',
    ];

    /**
     * 已上线（启用）且已到发布时间（released_at 为空视为立即生效）的版本。
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1)
            ->where(fn (Builder $q) => $q->whereNull('released_at')->orWhere('released_at', '<=', now()));
    }
}