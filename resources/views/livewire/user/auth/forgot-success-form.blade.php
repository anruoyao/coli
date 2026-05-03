<div>
    <form method="POST" wire:submit.prevent="submitForm">
        <div class="mb-16">
            <div class="mb-4">
                <x-auth.parts.form-header title="{{ __('auth.forgot_success_message.title') }}">
                    <x-slot:icon>
                        <x-ui-icon name="mail-01" type="line"></x-ui-icon>
                    </x-slot:icon>
                    <x-slot:caption>
                        {{ __('auth.forgot_success_message.caption', ['email_address' => $confirmationData->email]) }}
                    </x-slot:caption>
                </x-auth.parts.form-header>
            </div>
            
            <x-div></x-div>
        </div>
        
        <div class="block">
            @if($emailResent)
                <p class="text-par-s text-green-900 mb-2">
                    {{ __('auth.resend_link_success') }} &check;
                </p>
            @else
                <p class="text-par-s text-lab-pr2 mb-2">
                    {{ __('auth.resend_link_helper') }}
                </p>
            @endif
            <div class="mb-4">
                <x-auth.buttons.primary wire:loading.remove type="submit">
                    {{ __('auth.resend_link') }}

                    <x-slot:icon>
                        <x-ui-icon name="arrow-narrow-right" type="solid"></x-ui-icon>
                    </x-slot:icon>
                </x-auth.buttons.primary>
                <x-auth.buttons.loading wire:loading>
                </x-auth.buttons.loading>

                @error('resend-timeout')
                    <p class="text-cap-l text-red-900 mt-2">
                        {{ $message }}
                    </p>
                @enderror
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