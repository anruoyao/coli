<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            // 默认关闭：部署后由管理员在后台显式启用，避免上线即生效。
            $this->migrator->add('guest.enabled', false);
        });
    }
};
