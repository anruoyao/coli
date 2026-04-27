<div>
    <div class="mb-6">
        <x-auth.form.input 
            name="password" 
            :isPassword="true"
            wire:model.trim="password"
            placeholder="{{ __('auth.set_password') }}"></x-auth.form.input>
    </div>

    <div class="mb-6">
        <x-auth.form.input 
            name="password_confirmation" 
            :isPassword="true"
            wire:model.trim="password_confirmation"
            placeholder="{{ __('auth.confirm_password') }}"></x-auth.form.input>
        
        <p class="text-cap-l text-lab-sc mt-2">
            {{ __('auth.password_strength_helper', ['min_length' => config('user.validation.password.min')]) }}
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
