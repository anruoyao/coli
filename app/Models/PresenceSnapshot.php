<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 在线量小时快照（presence_snapshots）。
 *
 * 每小时一个桶（window_start 唯一，UTC），记录该时刻在线会话数（总 + 分平台），
 * 供后台「数据分析」趋势图使用。
 */
class PresenceSnapshot extends Model
{
    public $guarded = [];

    protected $casts = [
        'window_start' => 'datetime',
        'total_count' => 'integer',
        'web_count' => 'integer',
        'android_count' => 'integer',
        'ios_count' => 'integer',
    ];
}