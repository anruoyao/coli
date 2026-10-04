<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ReportNotificationSettings extends Settings
{
    // 举报邮件通知总开关（关闭后新举报不再向管理员邮箱发送通知）
    public bool $enabled;

    public static function group(): string
    {
        return 'report_notification';
    }
}
