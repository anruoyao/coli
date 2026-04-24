<div>
    <form method="POST" wire:submit.prevent="submitForm">
        <div class="mb-6">
            <x-auth.parts.form-header title="{{ __('auth.signup_for_app.title', ['app_name' => config('app.name')]) }}">
                <x-slot:caption>
                    {{ __('auth.signup_for_app.caption') }}
                </x-slot:caption>
            </x-auth.parts.form-header>
        </div>
        
        <div class="block">
            @include('livewire.user.auth.parts.social-buttons')

            <div class="mb-3">
                <x-auth.form.input 
                    name="emailAddress" 
                    wire:model.trim="emailAddress"
                placeholder="{{ __('auth.enter_email') }}"></x-auth.form.input>
            </div>

            <div class="mb-4">
                <x-auth.buttons.primary wire:loading.remove type="submit">
                    {{ __('auth.email_continue') }}
                </x-auth.buttons.primary>
                <x-auth.buttons.loading wire:loading>
                </x-auth.buttons.loading>
            </div>
            <div class="mb-6">
                <a href="{{ route('user.auth.index') }}" class="text-center block text-brand-900 text-par-m underline">
                    {{ __('auth.already_have_account') }}
                </a>
            </div>
            
            @include('livewire.user.auth.parts.agreement')
        </div>
    </form>
</div>