<div>
    <div class="mb-3">
        <x-auth.form.input 
            name="userData.first_name" 
            wire:model.trim="userData.first_name"
            defaultValue="{{ $userData['first_name'] }}"
        placeholder="{{ __('labels.whats_name') }}"></x-auth.form.input>
    </div>

    @if ($microSteps['first_name'])
        <div class="mb-8">
            <x-auth.form.input 
                name="userData.last_name" 
                wire:model.trim="userData.last_name"
                defaultValue="{{ $userData['last_name'] }}"
            placeholder="{{ __('labels.whats_last_name') }}"></x-auth.form.input>
        </div>

        @if ($microSteps['last_name'])
            <div class="mb-6" x-cloak>
                <span class="text-par-m font-normal text-lab-pr2 mb-2 block">
                    {{ __('labels.birthdate') }}
                </span>
                <div class="grid grid-cols-3 gap-2">
                    <div class="cursor-pointer">
                        <x-auth.form.select
                            :hasLabel="false"
                            action="saveBirthMonth"
                            :options="$birthMonthsList"
                        placeholder="{{ (me()->birth_month) ? __('labels.months.' . me()->birth_month) : __('labels.month') }}"></x-auth.form.select>
                    </div>
                    <div class="cursor-pointer">
                        <x-auth.form.select
                            :hasLabel="false"
                            action="saveBirthDay"
                            :options="$birthDaysList"
                        placeholder="{{ (me()->birth_day) ? me()->birth_day : __('labels.day') }}"></x-auth.form.select>
                    </div>
                    <div class="cursor-pointer">
                        <x-auth.form.select
                            :hasLabel="false"
                            action="saveBirthYear"
                            :options="$birthYearsList"
                        placeholder="{{ (me()->birth_year) ? me()->birth_year : __('labels.year') }}"></x-auth.form.select>
                    </div>
                </div>
                @error('birthdate')
                    <p class="text-cap-l text-red-900 mt-2">
                        {{ $message }}
                    </p>
                @else
                    <p class="text-cap-l text-lab-sc mt-2">
                        {{ __('labels.birthdate_input_helper')}}
                    </p>
                @endif
            </div>
            
            <div class="mb-6">
                <x-form.radio-group labelText="{{ __('labels.gender') }}">
                    <x-form.radio wire:change="saveGender('male')" :checked="me()->gender == 'male'" labelText="{{ __('labels.male') }}" name="userData.gender" defaultValue="male"></x-form.radio>
                    <x-form.radio wire:change="saveGender('female')" :checked="me()->gender == 'female'" labelText="{{ __('labels.female') }}" name="userData.gender" defaultValue="female"></x-form.radio>
                </x-form.radio-group>
                <p class="text-cap-l text-lab-sc mt-2">
                    {{ __('labels.not_public_info') }}
                </p>
            </div>
        @endif
    @endif

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
