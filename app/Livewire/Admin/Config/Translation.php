<?php

namespace App\Livewire\Admin\Config;

use App\Models\AdminConfigChangeLog;
use App\Services\Translation\Drivers\OpenAi;
use App\Services\Translation\OpenAiModelCatalog;
use App\Settings\TranslationSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Translation extends Component
{
    public array $formData = [];
    public array $availableModels = [];
    public ?string $modelLookupMessage = null;
    public string $modelLookupType = 'success';
    public ?string $flashContent = null;
    public string $flashType = 'success';

    private const LANGUAGES = [
        'auto', 'en', 'zh', 'ru', 'ja', 'ko', 'fr', 'de', 'es', 'pt', 'ar', 'it', 'tr', 'pl', 'uk',
        'en-us', 'en-gb', 'zh-cn', 'zh-hans',
    ];

    public function mount(): void
    {
        $this->authorizeAdmin();

        $settings = app(TranslationSettings::class);
        $this->formData = [
            'enabled' => $settings->enabled,
            'driver' => $settings->driver,
            'api_url' => $settings->api_url,
            'api_key' => '',
            'api_key_set' => (string) ($settings->api_key ?? '') !== '',
            'default_source' => $settings->default_source,
            'default_target' => $settings->default_target,
            'timeout' => $settings->timeout,
            'connect_timeout' => $settings->connect_timeout,
            'max_text_length' => $settings->max_text_length,
            'threads' => $settings->threads,
            'compute_type' => $settings->compute_type,
            'beam_size' => $settings->beam_size,
            'max_input_tokens' => $settings->max_input_tokens,
            'max_output_tokens' => $settings->max_output_tokens,
            'max_concurrent_requests' => $settings->max_concurrent_requests,
            'global_rate_per_minute' => $settings->global_rate_per_minute,
            'rate_per_minute' => $settings->rate_per_minute,
            'model' => $settings->model,
            'system_prompt' => $settings->system_prompt,
            'pretranslate_enabled' => $settings->pretranslate_enabled,
            'openai_concurrency' => $settings->openai_concurrency,
        ];
    }

    public function saveSettings(): void
    {
        $this->authorizeAdmin();
        $this->validate([
            'formData.enabled' => ['required', 'boolean'],
            'formData.driver' => ['required', 'in:nllb,libretranslate,openai'],
            'formData.api_url' => ['required', 'url:http,https', 'max:255'],
            'formData.default_source' => ['required', Rule::in(self::LANGUAGES)],
            'formData.default_target' => ['required', Rule::in(array_values(array_diff(self::LANGUAGES, ['auto'])))],
            'formData.timeout' => ['required', 'integer', 'min:1', 'max:120'],
            'formData.connect_timeout' => ['required', 'integer', 'min:1', 'max:30'],
            'formData.max_text_length' => ['required', 'integer', 'min:1', 'max:10000'],
            'formData.api_key' => ['nullable', 'string', 'max:255'],
            'formData.threads' => ['required', 'integer', 'min:1', 'max:8'],
            'formData.compute_type' => ['required', Rule::in(['int8', 'int8_float32', 'float32'])],
            'formData.beam_size' => ['required', 'integer', 'min:1', 'max:8'],
            'formData.max_input_tokens' => ['required', 'integer', 'min:1', 'max:512'],
            'formData.max_output_tokens' => ['required', 'integer', 'min:1', 'max:512'],
            'formData.max_concurrent_requests' => ['required', 'integer', 'min:1', 'max:4'],
            'formData.global_rate_per_minute' => ['required', 'integer', 'min:1', 'max:1200'],
            'formData.rate_per_minute' => ['required', 'integer', 'min:1', 'max:10000'],
            'formData.model' => ['required', 'string', 'max:120'],
            'formData.system_prompt' => ['required', 'string', 'min:1', 'max:4000'],
            'formData.pretranslate_enabled' => ['required', 'boolean'],
            'formData.openai_concurrency' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $settings = app(TranslationSettings::class);
        $runtimeKeys = [
            'threads', 'compute_type', 'beam_size', 'max_input_tokens', 'max_output_tokens',
            'max_concurrent_requests', 'global_rate_per_minute',
        ];
        $runtime = [];
        foreach ($runtimeKeys as $key) {
            $runtime[$key] = is_numeric($this->formData[$key])
                ? (int) $this->formData[$key]
                : trim((string) $this->formData[$key]);
        }

        $runtimeApplied = false;
        $previousRuntime = [];
        try {
            if ((bool) $this->formData['enabled'] && $this->formData['driver'] === 'nllb') {
                foreach ($runtimeKeys as $key) {
                    $previousRuntime[$key] = $settings->{$key};
                }
                try {
                    $actual = $this->runtimeRequest(
                        (int) $this->formData['timeout'],
                        (int) $this->formData['connect_timeout']
                    )->get(rtrim((string) config('services.translation.runtime_url'), '/') . '/runtime')
                        ->throw()->json();
                    foreach ($runtimeKeys as $key) {
                        if (array_key_exists($key, $actual)) {
                            $previousRuntime[$key] = $actual[$key];
                        }
                    }
                } catch (\Throwable) {
                    // Keep the persisted settings as a rollback fallback.
                }
                $response = $this->runtimeRequest(
                    (int) $this->formData['timeout'],
                    (int) $this->formData['connect_timeout']
                )->put(rtrim((string) config('services.translation.runtime_url'), '/') . '/runtime', $runtime)->throw();
                $body = $response->json();
                if (($body['ready'] ?? false) === true) {
                    $runtimeApplied = true;
                }
                if (($body['ready'] ?? false) !== true || ! $this->runtimeMatches($body, $runtime)) {
                    throw new \RuntimeException('Runtime did not confirm the requested configuration.');
                }
            }

            $keys = [
                'enabled', 'driver', 'api_url', 'default_source', 'default_target', 'timeout',
                'connect_timeout', 'max_text_length', 'rate_per_minute', 'model', 'system_prompt',
                'pretranslate_enabled', 'openai_concurrency', ...$runtimeKeys,
            ];
            $old = $this->settingSnapshot($settings, $keys);
            foreach ([
                'enabled', 'driver', 'api_url', 'default_source', 'default_target', 'timeout', 'connect_timeout',
                'max_text_length', 'rate_per_minute', 'model', 'system_prompt', 'pretranslate_enabled', 'openai_concurrency',
            ] as $key) {
                $value = $this->formData[$key];
                $settings->{$key} = is_numeric($value) ? (int) $value : (is_bool($value) ? (bool) $value : trim((string) $value));
            }
            foreach ($runtime as $key => $value) {
                $settings->{$key} = $value;
            }
            $apiKey = (string) ($this->formData['api_key'] ?? '');
            if ($apiKey !== '') {
                $settings->api_key = $apiKey;
            }
            DB::transaction(function () use ($settings, $old, $keys): void {
                $settings->save();
                $new = $this->settingSnapshot($settings, $keys);
                AdminConfigChangeLog::record('translation', 'settings_updated', [
                    'from' => $this->maskedAudit($old),
                    'to' => $this->maskedAudit($new),
                ]);
            });
            $this->flashType = 'success';
            $this->flashContent = __('admin/flash.config.settings_success');
            $this->formData['api_key'] = '';
            $this->formData['api_key_set'] = (string) ($settings->api_key ?? '') !== '';
        } catch (\Throwable) {
            if ($runtimeApplied) {
                try {
                    $this->runtimeRequest(
                        (int) $this->formData['timeout'],
                        (int) $this->formData['connect_timeout']
                    )->put(rtrim((string) config('services.translation.runtime_url'), '/') . '/runtime', $previousRuntime);
                } catch (\Throwable) {
                    // Best effort rollback; the saved settings remain unchanged.
                }
            }
            $this->flashType = 'error';
            $this->flashContent = __('admin/flash.config.settings_error');
        }
    }

    public function updated(string $propertyName, mixed $value): void
    {
        if (in_array($propertyName, ['formData.api_url', 'formData.api_key', 'formData.driver'], true)) {
            $this->queryModels();
        }
    }

    public function queryModels(): void
    {
        $this->authorizeAdmin();
        $this->availableModels = [];
        $this->modelLookupMessage = null;

        if (($this->formData['driver'] ?? '') !== 'openai') {
            return;
        }

        $apiUrl = trim((string) ($this->formData['api_url'] ?? ''));
        if ($apiUrl === '' || ! filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            return;
        }

        $settings = app(TranslationSettings::class);
        $apiKey = trim((string) ($this->formData['api_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = trim((string) ($settings->api_key ?? ''));
        }
        if ($apiKey === '') {
            return;
        }

        try {
            $this->availableModels = app(OpenAiModelCatalog::class)->list(
                $apiUrl,
                $apiKey,
                min(max((int) ($this->formData['timeout'] ?? 15), 1), 120),
                min(max((int) ($this->formData['connect_timeout'] ?? 2), 1), 30),
            );
            $this->modelLookupType = 'success';
            $this->modelLookupMessage = $this->availableModels === []
                ? __('admin/config.form.translation_models_empty')
                : trans_choice('admin/config.form.translation_models_found', count($this->availableModels), [
                    'count' => count($this->availableModels),
                ]);
        } catch (\Throwable) {
            $this->modelLookupType = 'error';
            $this->modelLookupMessage = __('admin/config.form.translation_models_failed');
        }
    }

    public function testConnection(): void
    {
        $this->authorizeAdmin();
        $timeout = min(max((int) ($this->formData['timeout'] ?? 15), 1), 120);
        $connectTimeout = min(max((int) ($this->formData['connect_timeout'] ?? 2), 1), 30);
        try {
            if (($this->formData['driver'] ?? '') === 'openai') {
                validator($this->formData, [
                    'api_url' => ['required', 'url:http,https', 'max:255'],
                    'model' => ['required', 'string', 'max:120'],
                    'system_prompt' => ['required', 'string', 'min:1', 'max:4000'],
                ])->validate();
                $settings = app(TranslationSettings::class);
                $previous = config('services.translation');
                config(['services.translation' => array_merge($previous, [
                    'enabled' => true,
                    'service' => 'openai',
                    'api_url' => trim((string) $this->formData['api_url']),
                    'api_key' => (string) (($this->formData['api_key'] ?? '') ?: $settings->api_key),
                    'model' => trim((string) $this->formData['model']),
                    'system_prompt' => trim((string) $this->formData['system_prompt']),
                    'timeout' => $timeout,
                    'connect_timeout' => $connectTimeout,
                    'openai_concurrency' => min(max((int) ($this->formData['openai_concurrency'] ?? 5), 1), 5),
                ])]);
                try {
                    app(OpenAi::class)->from('en')->to('en')->translate('Reply with OK.');
                } finally {
                    config(['services.translation' => $previous]);
                }
            } else {
                $this->runtimeRequest($timeout, $connectTimeout)
                    ->get(rtrim((string) config('services.translation.runtime_url'), '/') . '/runtime/health')
                    ->throw();
            }
            $this->flashType = 'success';
            $this->flashContent = __('admin/config.form.translation_connection_ok');
        } catch (\Throwable) {
            $this->flashType = 'error';
            $this->flashContent = __('admin/config.form.translation_connection_failed');
        }
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth_check() && (me()->isAdmin() || me()->isRoot()), 403);
    }

    private function runtimeRequest(int $timeout = 15, int $connectTimeout = 2)
    {
        $request = Http::acceptJson()->asJson()->timeout($timeout)->connectTimeout($connectTimeout);
        $key = (string) (config('services.translation.runtime_key') ?? '');
        if ($key !== '') {
            $request = $request->withToken($key);
        }

        return $request;
    }

    private function runtimeMatches(array $actual, array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $actual) || (string) $actual[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private function settingSnapshot(TranslationSettings $settings, array $keys): array
    {
        $snapshot = [];
        foreach ($keys as $key) {
            $snapshot[$key] = $settings->{$key};
        }

        return $snapshot;
    }

    private function maskedAudit(array $values): array
    {
        unset($values['api_key']);
        if (isset($values['system_prompt'])) {
            $values['system_prompt_sha256'] = hash('sha256', $values['system_prompt']);
            unset($values['system_prompt']);
        }

        return $values;
    }

    public function render()
    {
        return view('livewire.admin.config.translation');
    }
}
