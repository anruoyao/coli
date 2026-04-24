<div>
    <div class="mb-4 cursor-pointer" x-cloak>
        <x-auth.form.select
            :hasLabel="false"
            action="saveCountry"
            :options="$worldCountries"
        placeholder="{{ me()->country ? me('country_name') : __('labels.whats_country') }}"></x-auth.form.select>

        @error('country')
            <p class="text-cap-l text-red-900 mt-2">
                {{ $message }}
            </p>
        @endif
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
    <div class="mb-8">
        <button type="button" wire:click="skipStep" class="text-lab-sc hover:text-brand-900 text-par-s font-medium block w-full text-center underline">
            {{ __('labels.skip_step') }}
        </button>
    </div>
</div>
