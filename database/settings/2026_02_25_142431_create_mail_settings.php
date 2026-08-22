<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('mail.transport', 'smtp');
            $this->migrator->add('mail.host', 'localhost');
            $this->migrator->add('mail.port', 2525);
            $this->migrator->add('mail.timeout', 60);
            $this->migrator->add('mail.username', 'username');
            $this->migrator->add('mail.password', 'password');
            $this->migrator->add('mail.encryption', 'tls');
            $this->migrator->add('mail.from_address', 'noreply@example.com');
            $this->migrator->add('mail.from_name', 'Example');
            $this->migrator->add('mail.local_domain', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST));
        });
    }
};
