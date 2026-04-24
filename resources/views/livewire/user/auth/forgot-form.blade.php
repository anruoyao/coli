<div>
    <form method="POST" wire:submit.prevent="submitForm">
        <div class="mb-6">
            <x-auth.parts.form-header title="{{ __('auth.restore_access')}}">
                <x-slot:icon>
                    <x-ui-icon name="lock-unlocked-02" type="line"></x-ui-icon>
                </x-slot:icon>
                <x-slot:caption>
                    {{ __('auth.restore_access_helper', ['app_name' => config('app.name')]) }}
                </x-slot:caption>
            </x-auth.parts.form-header>
        </div>
        
        <div class="block">
            <div class="mb-3">
                <x-auth.form.input 
                    name="emailAddress" 
                    wire:model.trim="emailAddress"
                    defaultValue=""
                placeholder="{{ __('auth.enter_email') }}"></x-auth.form.input>
            </div>

            <div class="mb-4">
                <x-auth.buttons.primary wire:loading.remove type="submit">
                    {{ __('labels.continue') }}
                </x-auth.buttons.primary>
                <x-auth.buttons.loading wire:loading>
                </x-auth.buttons.loading>
            </div>
            <div class="mb-6">
                <a href="{{ route('user.auth.index') }}" class="text-center block text-brand-900 text-par-m underline">
                    {{ __('auth.back_to_login') }}
                </a>
            </div>

            @include('livewire.user.auth.parts.agreement')
        </div>
    </form>
</div>