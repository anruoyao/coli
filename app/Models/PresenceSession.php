<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 在线状态会话（presence_sessions）。
 *
 * 会话级在线明细：同一用户可多端（web/android/ios）并发在线。
 * 在线判定：last_seen_at >= now()-window 且 is_background = 0（窗口见 config('user.online_interval_in_minutes')）。
 * 与 User::isOnline()（用户级，users.last_active）互补，不改变既有语义。
 */
class PresenceSession extends Model
{
    public const PLATFORM = [
        'web' => 'Web',
        'android' => 'Android',
        'ios' => 'iOS',
    ];

    public $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'is_background' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 在线窗口：last_seen_at 在窗口内且非后台标记。
     */
    public function scopeOnline(Builder $query): Builder
    {
        $window = (int) config('user.online_interval_in_minutes', 5);

        return $query->where('last_seen_at', '>=', now()->subMinutes($window))
            ->where('is_background', 0);
    }

    public function isOnline(): bool
    {
        if (! $this->last_seen_at || (bool) $this->is_background) {
            return false;
        }

        $window = (int) config('user.online_interval_in_minutes', 5);

        return $this->last_seen_at->isGreaterThanOrEqualTo(now()->subMinutes($window));
    }
}