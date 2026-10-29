<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $defaults = array_merge(App\Settings\TranslationSettings::defaults(), [
                'enabled' => (bool) config('services.translation.enabled'),
                'driver' => (string) config('services.translation.service', 'nllb'),
                'api_url' => (string) config('services.translation.api_url'),
                'api_key' => (string) config('services.translation.api_key', ''),
                'default_source' => (string) config('services.translation.default_source', 'auto'),
                'default_target' => (string) config('services.translation.default_target', 'en'),
                'timeout' => (int) config('services.translation.timeout', 15),
                'connect_timeout' => (int) config('services.translation.connect_timeout', 2),
                'max_text_length' => (int) config('services.translation.max_text_length', 2000),
                'rate_per_minute' => (int) env('SECURITY_RATE_TRANSLATOR', 10),
            ]);

            foreach ($defaults as $name => $value) {
                if ($this->migrator->exists("translation.{$name}")) {
                    continue;
                }

                if ($name === 'api_key') {
                    $this->migrator->addEncrypted("translation.{$name}", $value);
                } else {
                    $this->migrator->add("translation.{$name}", $value);
                }
            }
        });
    }
};
