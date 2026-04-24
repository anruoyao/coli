@extends('adminLayout::index')

@section('pageContent')
	<div class="mb-4">
		<x-page-title titleText=" {{ __('admin/config.api_settings') }}"></x-page-title>
		<x-page-desc>
			{{ __('admin/config.api_settings_desc') }}
		</x-page-desc>
	</div>

	<x-sided-content>
		<x-slot:sideContent>
			<x-config.readonly-notice></x-config.readonly-notice>
		</x-slot:sideContent>

		<div class="flex flex-col gap-6">
			<x-config.env
				name="APP_API_KEY"
				description="{{ __('admin/config.captions.api_key') }}"
			value="{{ config('app.api_key') }}"/>
		</div>
	</x-sided-content>
@endsection