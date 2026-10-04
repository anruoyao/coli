<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class ReportNotificationEmail extends Model
{
    public $table = Table::REPORT_NOTIFICATION_EMAILS;

    protected $guarded = [];

    protected $casts = [
        // Laravel encrypted cast：数据库中密文存储，APP_KEY 解密读取
        'email' => 'encrypted',
        'enabled' => 'boolean',
    ];

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * 后台展示用的脱敏形式（ab***@example.com）。
     */
    public function getMaskedEmailAttribute(): string
    {
        $email = $this->email ?? '';
        $parts = explode('@', $email);

        if(count($parts) !== 2) {
            return '***';
        }

        [$name, $domain] = $parts;
        $length = mb_strlen($name);

        if($length <= 2) {
            return '***@' . $domain;
        }

        return mb_substr($name, 0, 2) . '***@' . $domain;
    }
}
