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

        <x-accordion.form title="{{ __('admin/marketing.form.rich') }}" :open="false">
            <div class="space-y-6">
                <x-form.text-input
                    :labelText="__('admin/marketing.form.image_url')"
                    name="image_url"
                    :value="old('image_url')"
                    :errorKey="'image_url'"
                    placeholder="{{ __('admin/marketing.placeholders.image_url') }}">
                    <x-slot:feedbackInfo>
                        {{ __('admin/marketing.form.image_url_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>

                <div>
                    <x-form.label>{{ __('admin/marketing.form.title_size') }}</x-form.label>
                    <div class="relative">
                        <select
                            name="title_size"
                            id="title_size"
                            class="block w-full bg-input-pr outline-hidden text-par-m text-lab-pr px-4 h-12 rounded-xl border-2 border-transparent hover:border-brand-900 smoothing">
                            <option value="sm" {{ old('title_size') === 'sm' ? 'selected' : '' }}>{{ __('admin/marketing.title_sizes.sm') }}</option>
                            <option value="md" {{ old('title_size', 'md') === 'md' ? 'selected' : '' }}>{{ __('admin/marketing.title_sizes.md') }}</option>
                            <option value="lg" {{ old('title_size') === 'lg' ? 'selected' : '' }}>{{ __('admin/marketing.title_sizes.lg') }}</option>
                        </select>
                    </div>
                    @error('title_size')
                        <div class="mt-2"><x-form.valerr>{{ $message }}</x-form.valerr></div>
                    @enderror
                </div>

                <div>
                    <x-form.label>{{ __('admin/marketing.form.title_weight') }}</x-form.label>
                    <div class="relative">
                        <select
                            name="title_weight"
                            id="title_weight"
                            class="block w-full bg-input-pr outline-hidden text-par-m text-lab-pr px-4 h-12 rounded-xl border-2 border-transparent hover:border-brand-900 smoothing">
                            <option value="normal" {{ old('title_weight') === 'normal' ? 'selected' : '' }}>{{ __('admin/marketing.title_weights.normal') }}</option>
                            <option value="medium" {{ old('title_weight') === 'medium' ? 'selected' : '' }}>{{ __('admin/marketing.title_weights.medium') }}</option>
                            <option value="semibold" {{ old('title_weight', 'semibold') === 'semibold' ? 'selected' : '' }}>{{ __('admin/marketing.title_weights.semibold') }}</option>
                            <option value="bold" {{ old('title_weight') === 'bold' ? 'selected' : '' }}>{{ __('admin/marketing.title_weights.bold') }}</option>
                        </select>
                    </div>
                    @error('title_weight')
                        <div class="mt-2"><x-form.valerr>{{ $message }}</x-form.valerr></div>
                    @enderror
                </div>

                <div>
                    <x-form.text-input
                        :labelText="__('admin/marketing.form.post_ids')"
                        name="post_ids"
                        :as-text="true"
                        :value="old('post_ids')"
                        :errorKey="'post_ids'"
                        placeholder="{{ __('admin/marketing.placeholders.post_ids') }}">
                        <x-slot:feedbackInfo>
                            {{ __('admin/marketing.form.post_ids_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.text-input>
                </div>
            </div>
        </x-accordion.form>

        <x-accordion.form title="{{ __('admin/marketing.form.channels') }}" :open="true">
            <div class="space-y-6">
                <div class="flex items-center leading-none select-none">
                    <label class="inline-flex items-center cursor-pointer leading-none">
                        <input type="checkbox" name="email_enabled" value="1" class="sr-only peer" {{ old('email_enabled', true) ? 'checked' : '' }}>
                        <div class="relative w-10 h-5 bg-[#787880]/20 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-[20px] rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:shadow-lg after:border-bord-card after:rounded-full after:size-4 after:transition-all peer-checked:bg-green-900"></div>
                        <span class="ml-3 text-lab-sc text-par-m">{{ __('admin/marketing.form.email_enabled') }}</span>
                    </label>
                </div>

                <div class="flex items-center leading-none select-none">
                    <label class="inline-flex items-center cursor-pointer leading-none">
                        <input type="checkbox" name="in_app_enabled" value="1" class="sr-only peer" {{ old('in_app_enabled', true) ? 'checked' : '' }}>
                        <div class="relative w-10 h-5 bg-[#787880]/20 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-[20px] rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:shadow-lg after:border-bord-card after:rounded-full after:size-4 after:transition-all peer-checked:bg-green-900"></div>
                        <span class="ml-3 text-lab-sc text-par-m">{{ __('admin/marketing.form.in_app_enabled') }}</span>
                    </label>
                </div>

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