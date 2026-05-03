<div>
    <form method="POST" wire:submit.prevent="submitForm">
        <div class="mb-6">
            <x-auth.parts.form-header title="{{ __('auth.login_to_app.title', ['app_name' => config('app.name')])}}">
                <x-slot:caption>
                    {{ __('auth.login_to_app.caption') }}
                </x-slot:caption>
            </x-auth.parts.form-header>
        </div>
        
        <div class="block">
            <div class="mb-3">
                <x-auth.buttons.primary variant="outline" tag="a" href="{{ route('user.auth.signup') }}">
                    {{ __('auth.create_account') }}
                </x-auth.buttons.primary>
            </div>

            @include('livewire.user.auth.parts.social-buttons')

            <div class="mb-3">
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

                    <div class="flex items-center leading-none mt-4 mb-4 select-none">
                        <label class="inline-flex items-center cursor-pointer leading-none">
                            <input type="checkbox" wire:model.trim="authCreds.remember" name="authCreds.remember" class="sr-only peer">
                            <div class="relative w-12 h-6 bg-[#787880]/20 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-[24px] rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:shadow-lg after:border after:rounded-full after:size-5 after:transition-all peer-checked:bg-brand-900"></div>
                            <span class="ml-3 text-lab-sc text-par-m">
                                {{ __('auth.remember_me') }}
                            </span>
                        </label>
                    </div>
                @endif
            </div>

            <div class="mb-4">
                <x-auth.buttons.primary wire:loading.remove type="submit">
                    {{ __('auth.email_continue') }}

                    <x-slot:icon>
                        <x-ui-icon name="arrow-narrow-right" type="solid"></x-ui-icon>
                    </x-slot:icon>
                </x-auth.buttons.primary>
                <x-auth.buttons.loading wire:loading>
                </x-auth.buttons.loading>

                
            </div>
            <div class="mb-6">
                <a href="{{ route('user.auth.forgot') }}" class="text-center block text-brand-900 text-par-m underline">
                    {{ __('auth.forgot_password') }}
                </a>
            </div>

            @include('livewire.user.auth.parts.agreement')
        </div>
    </form>
</div>