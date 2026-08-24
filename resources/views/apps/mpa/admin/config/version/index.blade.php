@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/version.index_title') }}"></x-page-title>

    <x-content>
        @livewire('admin.config.versions')
    </x-content>
@endsection