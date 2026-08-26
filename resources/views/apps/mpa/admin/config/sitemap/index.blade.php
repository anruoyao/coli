@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/sitemap.index_title') }}"></x-page-title>

    <x-content>
        @livewire('admin.config.sitemap')
    </x-content>
@endsection