<div>
    <form method="POST" wire:submit.prevent="submitForm">
        <div class="mb-6">
            <x-auth.parts.form-header title="{{ __('auth.linker_login.title')}}">
                <x-slot:icon>
                    <x-ui-icon name="user-right-01" type="line"></x-ui-icon>
                </x-slot:icon>
                <x-slot:caption>
                    {{ __('auth.linker_login.caption', ['app_name' => config('app.name')]) }}
                </x-slot:caption>
            </x-auth.parts.form-header>
        </div>
        
        <div class="block">
            <div class="mb-3">
                @if($loginStep == 2)
                    <div class="border px-4 rounded-md flex h-14 items-center overflow-hidden mb-3">
                        <div class="size-small-avatar overflow-hidden rounded-full">
                            <img src="{{ $userData->avatar_url }}" alt="Avatar Image">
                        </div>
                        <div class="ml-3 flex-1 truncate">
                            <span class="text-lab-pr2 text-par-m font-medium">{{ $userData->name }} <span class="text-lab-sc">({{ $userData->username }})</span></span>
                        </div>
                    </div>
                @endif
                <x-auth.form.input 
                    name="authCreds.login" 
                    wire:model.trim="authCreds.login"
                    defaultValue=""
                placeholder="{{ __('auth.login_or_email') }}"></x-auth.form.input>
                
                @if($loginStep == 2)
                    
                    <div class="mt-3">
                        <x-auth.form.input
                            name="authCreds.password"
                            :isPassword="true"
                            wire:model.trim="authCreds.password"
                        placeholder="{{ __('auth.enter_password') }}"></x-auth.form.input>
                    </div>
                @endif
            </div>

            <div class="mb-4">
                <x-auth.buttons.primary wire:loading.remove type="submit">
                    {{ __('auth.linker_login.button') }}
                </x-auth.buttons.primary>
                <x-auth.buttons.loading wire:loading>
                </x-auth.buttons.loading>
            </div>
            <div class="mb-6">
                <a href="{{ route('user.desktop.index') }}" class="text-center block text-brand-900 text-par-m underline">
                    {{ __('labels.back_to_home') }}
                </a>
            </div>

            @include('livewire.user.auth.parts.agreement')
        </div>
    </form>
</div>