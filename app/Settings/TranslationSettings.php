<?php

namespace App\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

class TranslationSettings extends Settings
{
    public bool $enabled;
    public string $driver;
    public string $api_url;
    #[ShouldBeEncrypted]
    public string $api_key;
    public string $default_source;
    public string $default_target;
    public int $timeout;
    public int $connect_timeout;
    public int $max_text_length;
    public int $threads;
    public string $compute_type;
    public int $beam_size;
    public int $max_input_tokens;
    public int $max_output_tokens;
    public int $max_concurrent_requests;
    public int $global_rate_per_minute;
    public int $rate_per_minute;
    public string $model;
    public string $system_prompt;
    public bool $pretranslate_enabled;
    public int $openai_concurrency;

    public static function group(): string
    {
        return 'translation';
    }

    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'driver' => 'nllb',
            'api_url' => 'http://127.0.0.1:8301',
            'api_key' => '',
            'default_source' => 'auto',
            'default_target' => 'en',
            'timeout' => 15,
            'connect_timeout' => 2,
            'max_text_length' => 2000,
            'threads' => 2,
            'compute_type' => 'int8',
            'beam_size' => 2,
            'max_input_tokens' => 512,
            'max_output_tokens' => 512,
            'max_concurrent_requests' => 1,
            'global_rate_per_minute' => 10,
            'rate_per_minute' => 10,
            'model' => 'gpt-4o-mini',
            'system_prompt' => 'Translate the source text naturally and accurately. Preserve its meaning, tone, names, and formatting. Return only the translation.',
            'pretranslate_enabled' => false,
            'openai_concurrency' => 5,
        ];
    }
}
