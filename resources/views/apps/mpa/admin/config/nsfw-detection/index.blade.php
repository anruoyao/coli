@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/config.nsfw_detection_settings') }}"></x-page-title>

    <x-content width="w-full">
        @livewire('admin.config.nsfw-detection')
    </x-content>
@endsection
