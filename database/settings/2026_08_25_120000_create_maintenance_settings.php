<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('maintenance.enabled', false);
            $this->migrator->add('maintenance.message', '');
            $this->migrator->add('maintenance.until', null);
        });
    }
};