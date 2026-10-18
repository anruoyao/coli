@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/config.translation_settings') }}"></x-page-title>

    <x-content width="w-full">
        @livewire('admin.config.translation')
    </x-content>
@endsection
