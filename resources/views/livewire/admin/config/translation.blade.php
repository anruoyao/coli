<div class="flex flex-col gap-6">
    @if($flashContent)
        <div class="rounded-xl px-4 py-3 {{ $flashType === 'error' ? 'bg-red-500/10 text-red-900' : 'bg-green-500/10 text-green-900' }}">{{ $flashContent }}</div>
    @endif

    <x-form.group>
        <x-form.switcher labelText="{{ __('admin/config.form.translation_enabled') }}" wire:model="formData.enabled" name="formData.enabled" />
    </x-form.group>

    <x-form.group>
        <x-form.label>{{ __('admin/config.form.translation_driver') }}</x-form.label>
        <select wire:model="formData.driver" name="formData.driver" class="block w-full bg-input-pr px-4 h-12 rounded-xl">
            <option value="nllb">NLLB-200 + CTranslate2</option>
            <option value="libretranslate">LibreTranslate</option>
        </select>
    </x-form.group>

    <x-form.group>
        <x-form.text-input labelText="{{ __('admin/config.form.translation_api_url') }}" wire:model="formData.api_url" name="formData.api_url" />
    </x-form.group>

    <x-form.group>
        <x-form.text-input labelText="{{ __('admin/config.form.translation_api_key') }}{{ !empty($formData['api_key_set']) ? ' ('.__('admin/config.form.translation_key_set').')' : '' }}" wire:model="formData.api_key" type="password" :isPassword="true" name="formData.api_key" />
    </x-form.group>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-form.group>
            <x-form.text-input labelText="{{ __('admin/config.form.translation_source') }}" wire:model="formData.default_source" name="formData.default_source" />
        </x-form.group>
        <x-form.group>
            <x-form.text-input labelText="{{ __('admin/config.form.translation_target') }}" wire:model="formData.default_target" name="formData.default_target" />
        </x-form.group>
        <x-form.group>
            <x-form.text-input labelText="{{ __('admin/config.form.translation_timeout') }}" wire:model="formData.timeout" type="number" name="formData.timeout" />
        </x-form.group>
        <x-form.group>
            <x-form.text-input labelText="{{ __('admin/config.form.translation_connect_timeout') }}" wire:model="formData.connect_timeout" type="number" name="formData.connect_timeout" />
        </x-form.group>
        <x-form.group>
            <x-form.text-input labelText="{{ __('admin/config.form.translation_max_length') }}" wire:model="formData.max_text_length" type="number" name="formData.max_text_length" />
        </x-form.group>
        <x-form.group><x-form.text-input labelText="{{ __('admin/config.form.translation_threads') }}" wire:model="formData.threads" type="number" name="formData.threads" /></x-form.group>
        <x-form.group><x-form.text-input labelText="{{ __('admin/config.form.translation_compute_type') }}" wire:model="formData.compute_type" name="formData.compute_type" /></x-form.group>
        <x-form.group><x-form.text-input labelText="{{ __('admin/config.form.translation_beam_size') }}" wire:model="formData.beam_size" type="number" name="formData.beam_size" /></x-form.group>
        <x-form.group><x-form.text-input labelText="{{ __('admin/config.form.translation_max_input_tokens') }}" wire:model="formData.max_input_tokens" type="number" name="formData.max_input_tokens" /></x-form.group>
        <x-form.group><x-form.text-input labelText="{{ __('admin/config.form.translation_max_output_tokens') }}" wire:model="formData.max_output_tokens" type="number" name="formData.max_output_tokens" /></x-form.group>
        <x-form.group><x-form.text-input labelText="{{ __('admin/config.form.translation_concurrency') }}" wire:model="formData.max_concurrent_requests" type="number" name="formData.max_concurrent_requests" /></x-form.group>
        <x-form.group><x-form.text-input labelText="{{ __('admin/config.form.translation_global_rate') }}" wire:model="formData.global_rate_per_minute" type="number" name="formData.global_rate_per_minute" /></x-form.group>
        <x-form.group><x-form.text-input labelText="{{ __('admin/config.form.translation_user_rate') }}" wire:model="formData.rate_per_minute" type="number" name="formData.rate_per_minute" /></x-form.group>
    </div>

    <div class="flex gap-3">
        <x-ui.buttons.pill size="sm" type="button" btnText="{{ __('admin/config.form.translation_test_connection') }}" wire:click="testConnection" wire:loading.attr="disabled" />
        <x-ui.buttons.pill size="sm" type="button" btnText="{{ __('buttons.save_changes') }}" wire:click="saveSettings" wire:loading.attr="disabled" />
    </div>
</div>
