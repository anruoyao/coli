@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/maintenance.index_title') }}"></x-page-title>

    <x-content>
        @livewire('admin.config.maintenance')
    </x-content>
@endsection