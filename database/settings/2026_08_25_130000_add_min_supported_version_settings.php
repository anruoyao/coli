<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 各平台「最低安全版本」。
 * 低于该版本的客户端请求将被 426 拦截并强制更新（空 = 不启用）。
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('app.min_supported_version_android', '');
            $this->migrator->add('app.min_supported_version_ios', '');
        });
    }
};