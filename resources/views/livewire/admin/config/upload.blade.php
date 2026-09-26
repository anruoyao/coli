<form wire:submit.prevent="submitForm">
    @csrf

    <x-form.group>
        <x-callout.default
            iconName="image-01"
            titleText="{{ __('admin/config.callout.image_max_mb.title') }}"
        captionText="{{ __('admin/config.callout.image_max_mb.caption') }}" />

        <x-form.text-input
            labelText="{{ __('admin/config.form.image_max_mb') }}"
            type="number"
            min="1"
            wire:model="formData.image_max_mb"
            name="formData.image_max_mb">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.upload_max_mb_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <div class="mb-6">
        <x-div/>
    </div>

    <x-form.group>
        <x-callout.default
            iconName="video-01"
            titleText="{{ __('admin/config.callout.video_max_mb.title') }}"
        captionText="{{ __('admin/config.callout.video_max_mb.caption') }}" />

        <x-form.text-input
            labelText="{{ __('admin/config.form.video_max_mb') }}"
            type="number"
            min="1"
            wire:model="formData.video_max_mb"
            name="formData.video_max_mb">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.upload_max_mb_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <div class="mb-6">
        <x-div/>
    </div>

    <x-form.group>
        <x-callout.default
            iconName="music-01"
            titleText="{{ __('admin/config.callout.audio_max_mb.title') }}"
        captionText="{{ __('admin/config.callout.audio_max_mb.caption') }}" />

        <x-form.text-input
            labelText="{{ __('admin/config.form.audio_max_mb') }}"
            type="number"
            min="1"
            wire:model="formData.audio_max_mb"
            name="formData.audio_max_mb">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.upload_max_mb_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <div class="mb-6">
        <x-div/>
    </div>

    <x-form.group cols="grid-cols-2">
        <x-form.text-input
            labelText="{{ __('admin/config.form.gif_max_mb') }}"
            type="number"
            min="1"
            wire:model="formData.gif_max_mb"
            name="formData.gif_max_mb">
        </x-form.text-input>

        <x-form.text-input
            labelText="{{ __('admin/config.form.document_max_mb') }}"
            type="number"
            min="1"
            wire:model="formData.document_max_mb"
            name="formData.document_max_mb">
        </x-form.text-input>
    </x-form.group>

    <x-ui.buttons.pill
        type="submit"
        size="sm"
        btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
