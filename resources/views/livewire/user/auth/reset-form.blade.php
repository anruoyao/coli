<div>
    <form method="POST" wire:submit.prevent="submitForm">
        <div class="mb-6">
            <x-auth.parts.form-header
                title="{{ __('auth.new_password')}}">

                <x-slot:caption>
                    {{ __('auth.new_password_helper') }}
                </x-slot:caption>
            </x-auth.parts.form-header>
        </div>
        
        <div class="block">
            <div class="border border-bord-pr gap-2.5 px-3 rounded-lg flex h-12 items-center overflow-hidden">
                <div class="size-7 overflow-hidden rounded-full">
                    <img src="{{ $userData->avatar_url }}" alt="Avatar Image">
                </div>
                <div class=flex-1 truncate">
                    <span class="text-lab-pr2 text-par-m font-medium">{{ $userData->name }} <span class="text-lab-sc">({{ $userData->username }})</span></span>
                </div>
            </div>
            <div class="mb-3">
                <div class="mt-3">
                    <x-auth.form.input
                        name="newPassword"
                        :isPassword="true"
                        wire:model.trim="newPassword"
                    placeholder="{{ __('auth.enter_new_password') }}"></x-auth.form.input>
                </div>
                <p class="text-par-s text-lab-sc mt-2">
                    {{ __('auth.password_strength_helper', ['min_length' => config('user.password_min')]) }}
                </p>
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