@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/config.upload_settings') }}"></x-page-title>

    <x-content width="w-full">
		@livewire('admin.config.upload')
	</x-content>
@endsection
