<div>
    <div class="mb-6">
        <x-auth.form.input 
            name="username" 
            wire:model.trim.debounce.500ms.live="username"
            defaultValue="{{ $username }}"
        placeholder="{{ __('labels.choose_username') }}"></x-auth.form.input>

        @if($isAvailable)
            <p class="text-cap-l text-green-900 mt-2">
                {{ __('validation.username_available') }} &check;
            </p>
        @elseif(empty($username) != true && ($username != me()->username))
            <p class="text-cap-l text-red-900 mt-2">
                {{ __('validation.username_unavailable') }}
            </p>
        @endif

        <p class="text-cap-l text-lab-sc mt-2">
            {{ __('labels.choose_username_helper')}}
        </p>
    </div>

    <div class="mb-4">
        <div class="block w-full" wire:loading.remove>
            <x-auth.buttons.primary type="button" wire:click="submitForm">
                {{ __('labels.continue') }}

                <x-slot:icon>
                    <x-ui-icon name="arrow-narrow-right" type="solid"></x-ui-icon>
                </x-slot:icon>
            </x-auth.buttons.primary>
        </div>
        
        <div class="block w-full" wire:loading>
            <x-auth.buttons.loading>
            </x-auth.buttons.loading>
        </div>
    </div>
</div>