@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/config.report_notifications_settings') }}"></x-page-title>

    <x-content width="w-full">
        @livewire('admin.config.report-notifications')
    </x-content>
@endsection
