@extends('adminLayout::index')

@section('pageContent')
	<div class="mb-4">
		<x-page-title titleText=" {{ __('admin/config.verification_settings') }}"></x-page-title>
		<x-page-desc>
			{{ __('admin/config.verification_settings_desc') }}
		</x-page-desc>
	</div>

	<x-sided-content>
		<x-slot:sideContent>
			<x-config.readonly-notice></x-config.readonly-notice>
		</x-slot:sideContent>
		
		<div class="flex flex-col gap-6">
			<x-config.env
				name="VERIFICATION_SERVICE_URL"
				description="{{ __('admin/config.captions.verification_service_url') }}"
			value="{{ config('verification.service_url') }}"/>
		</div>
	</x-sided-content>
@endsection