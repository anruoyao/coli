@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/presence.index_title') }}"></x-page-title>

    <x-content>
        @livewire('admin.presence.online-list')
    </x-content>
@endsection