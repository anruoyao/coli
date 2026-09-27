@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('admin/marketing.create_title') }}"></x-page-title>

    <form action="{{ route('admin.marketing.store') }}" method="POST">
        @csrf

        <x-accordion.form title="{{ __('admin/marketing.form.basic') }}" :open="true">
            <div class="space-y-6">
                <x-form.text-input
                    :labelText="__('admin/marketing.form.title') . ' *'"
                    name="title"
                    :value="old('title')"
                    :errorKey="'title'"
                    placeholder="{{ __('admin/marketing.placeholders.title') }}">
                </x-form.text-input>

                <x-form.text-input
                    :labelText="__('admin/marketing.form.content') . ' *'"
                    name="content"
                    :as-text="true"
                    :value="old('content')"
                    :errorKey="'content'"
                    placeholder="{{ __('admin/marketing.placeholders.content') }}">
                    <x-slot:feedbackInfo>
                        {{ __('admin/marketing.form.content_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>

                <x-form.text-input
                    :labelText="__('admin/marketing.form.landing_url')"
                    name="landing_url"
                    :value="old('landing_url')"
                    :errorKey="'landing_url'"
                    placeholder="{{ __('admin/marketing.placeholders.landing_url') }}">
                </x-form.text-input>
            </div>
        </x-accordion.form>

        <x-accordion.form title="{{ __('admin/marketing.form.channels') }}" :open="true">
            <div class="space-y-6">
                <x-form.switcher
                    :labelText="__('admin/marketing.form.email_enabled')"
                    name="email_enabled" value="1" {{ old('email_enabled', true) ? 'checked' : '' }}>
                </x-form.switcher>
                <x-form.switcher
                    :labelText="__('admin/marketing.form.in_app_enabled')"
                    name="in_app_enabled" value="1" {{ old('in_app_enabled', true) ? 'checked' : '' }}>
                </x-form.switcher>

                @if($errors->has('channels'))
                    <div>
                        <x-form.valerr>{{ $errors->first('channels') }}</x-form.valerr>
                    </div>
                @endif
                @if($errors->has('email_enabled'))
                    <div>
                        <x-form.valerr>{{ $errors->first('email_enabled') }}</x-form.valerr>
                    </div>
                @endif

                <x-form.text-input
                    :labelText="__('admin/marketing.form.subject') . ' *'"
                    name="subject"
                    :value="old('subject')"
                    :errorKey="'subject'"
                    placeholder="{{ __('admin/marketing.placeholders.subject') }}">
                    <x-slot:feedbackInfo>
                        {{ __('admin/marketing.form.subject_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
        </x-accordion.form>

        <x-accordion.form title="{{ __('admin/marketing.form.target') }}" :open="true">
            <div class="space-y-6">
                <div>
                    <x-form.label>{{ __('admin/marketing.form.target_type') }} *</x-form.label>
                    <div class="relative">
                        <select
                            name="target_type"
                            id="target_type"
                            class="block w-full bg-input-pr outline-hidden text-par-m text-lab-pr px-4 h-12 rounded-xl border-2 border-transparent hover:border-brand-900 smoothing">
                            <option value="all" {{ old('target_type') === 'all' ? 'selected' : '' }}>{{ __('admin/marketing.targets.all') }}</option>
                            <option value="manual" {{ old('target_type') === 'manual' ? 'selected' : '' }}>{{ __('admin/marketing.targets.manual') }}</option>
                            <option value="type" {{ old('target_type') === 'type' ? 'selected' : '' }}>{{ __('admin/marketing.targets.type') }}</option>
                        </select>
                    </div>
                    @error('target_type')
                        <div class="mt-2"><x-form.valerr>{{ $message }}</x-form.valerr></div>
                    @enderror
                </div>

                <div>
                    <x-form.text-input
                        :labelText="__('admin/marketing.form.target_user_ids')"
                        name="target_user_ids"
                        :as-text="true"
                        :value="old('target_user_ids')"
                        :errorKey="'target_user_ids'"
                        placeholder="{{ __('admin/marketing.placeholders.target_user_ids') }}">
                        <x-slot:feedbackInfo>
                            {{ __('admin/marketing.form.target_user_ids_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.text-input>
                </div>

                <div>
                    <x-form.label>{{ __('admin/marketing.form.target_user_type') }}</x-form.label>
                    <div class="relative">
                        <select
                            name="target_user_type"
                            id="target_user_type"
                            class="block w-full bg-input-pr outline-hidden text-par-m text-lab-pr px-4 h-12 rounded-xl border-2 border-transparent hover:border-brand-900 smoothing">
                            <option value="">{{ __('admin/marketing.form.target_user_type_none') }}</option>
                            <option value="author" {{ old('target_user_type') === 'author' ? 'selected' : '' }}>{{ __('admin/marketing.targets.type_author') }}</option>
                            <option value="reader" {{ old('target_user_type') === 'reader' ? 'selected' : '' }}>{{ __('admin/marketing.targets.type_reader') }}</option>
                        </select>
                    </div>
                    @error('target_user_type')
                        <div class="mt-2"><x-form.valerr>{{ $message }}</x-form.valerr></div>
                    @enderror
                </div>
            </div>
        </x-accordion.form>

        <div class="mt-6">
            <x-ui.buttons.pill size="md" type="submit" btnText="{{ __('admin/marketing.create_button') }}"></x-ui.buttons.pill>
        </div>
    </form>
@endsection