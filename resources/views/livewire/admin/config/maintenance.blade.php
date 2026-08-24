<form wire:submit.prevent="submitForm">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <x-form.group class="md:col-span-2">
            <div class="flex items-center justify-between p-4 rounded-2xl bg-input-pr border {{ $formData['enabled'] ? 'border-red-900' : 'border-bord-sc' }}">
                <div>
                    <p class="text-par-m font-medium text-lab-pr">{{ __('admin/maintenance.form.enabled') }}</p>
                    <p class="text-par-s text-lab-sc mt-1">{{ __('admin/maintenance.form.enabled_helper') }}</p>
                </div>
                <x-form.switcher
                    :checked="$formData['enabled']"
                    wire:model="formData.enabled">
                </x-form.switcher>
            </div>
        </x-form.group>

        <x-form.group class="md:col-span-2">
            <x-form.text-input
                asText
                labelText="{{ __('admin/maintenance.form.message') }}"
                wire:model="formData.message"
                name="formData.message"
                :placeholder="__('admin/maintenance.form.message_placeholder')">
                <x-slot:feedbackInfo>
                    {{ __('admin/maintenance.form.message_helper') }}
                </x-slot:feedbackInfo>
            </x-form.text-input>
        </x-form.group>

        <x-form.group>
            <x-form.text-input
                labelText="{{ __('admin/maintenance.form.until') }}"
                inputType="datetime-local"
                wire:model="formData.until"
                name="formData.until">
                <x-slot:feedbackInfo>
                    {{ __('admin/maintenance.form.until_helper') }}
                </x-slot:feedbackInfo>
            </x-form.text-input>
        </x-form.group>
    </div>

    <div class="mt-6">
        <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
    </div>
</form>