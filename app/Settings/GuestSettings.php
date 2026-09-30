<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 访客（Guest）功能设置。
 *
 * 目前仅含总开关：enabled=false 时访客 API 返回 403，未登录真人访问网站
 * 恢复旧行为（公开路径 SEO HTML、其余重定向登录）。
 */
class GuestSettings extends Settings
{
    public bool $enabled;

    public static function group(): string
    {
        return 'guest';
    }
}
