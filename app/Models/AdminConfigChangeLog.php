<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminConfigChangeLog extends Model
{
    public $table = Table::ADMIN_CONFIG_CHANGE_LOGS;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id', 'id');
    }

    /**
     * 记录一次后台配置修改。
     */
    public static function record(string $configKey, string $action, array $changes = []): self
    {
        return static::create([
            'admin_id' => auth_check() ? me()->id : null,
            'config_key' => $configKey,
            'action' => $action,
            'changes' => $changes ?: null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
