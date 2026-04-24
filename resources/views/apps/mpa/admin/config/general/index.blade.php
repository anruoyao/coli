@extends('adminLayout::index')

@section('pageContent')
	<div class="mb-4">
		<x-page-title titleText=" {{ __('admin/config.general_settings') }}"></x-page-title>
		<x-page-desc>
			{{ __('admin/config.general_settings_desc') }}
		</x-page-desc>
	</div>

	<x-sided-content>
		<x-slot:sideContent>
			<x-config.readonly-notice></x-config.readonly-notice>
		</x-slot:sideContent>
		
		<div class="flex flex-col gap-6">
			<x-config.env
				name="APP_NAME"
				description="{{ __('admin/config.captions.app_name') }}"
			value="{{ config('app.name') }}"/>

			<x-config.env
				name="APP_URL"
				description="{{ __('admin/config.captions.app_url') }}"
			value="{{ config('app.url') }}"/>

			<x-config.env
				name="APP_DESCRIPTION"
				description="{{ __('admin/config.captions.app_description') }}"
			value="{{ config('app.description') }}"/>
			<x-config.env
				name="APP_KEYWORDS"
				description="{{ __('admin/config.captions.app_keywords') }}"
			value="{{ config('app.keywords') }}"/>

			<x-config.env
				name="APP_TIMEZONE"
				description="{{ __('admin/config.captions.app_timezone') }}"
			value="{{ config('app.timezone') }}"/>

			<x-config.env
				name="APP_LOCALE"
				description="{{ __('admin/config.captions.app_locale') }}"
			value="{{ config('app.locale') }}"/>
		</div>
	</x-sided-content>
@endsection