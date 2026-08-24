<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 全局维护（应急）模式设置。
 * enabled=true 时：API 统一返回维护响应 + 广播 maintenance_on 让 App 进维护页。
 */
class MaintenanceSettings extends Settings
{
    public bool $enabled;
    public string $message;
    public ?string $until;

    public static function group(): string
    {
        return 'maintenance';
    }
}