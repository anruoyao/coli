@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/throttle.index_title') }}"></x-page-title>

    <x-content>
        @livewire('admin.throttle.throttle-monitor')
    </x-content>
@endsection
