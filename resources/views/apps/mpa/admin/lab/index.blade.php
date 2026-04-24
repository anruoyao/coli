@extends('adminLayout::index')

@section('pageContent')
    <div class="mb-6">
        <x-page-title titleText=" {{ __('admin/lab.index_title', ['app_name' => config('app.name')]) }}"></x-page-title>
        <x-page-desc>
            {{ __('admin/lab.index_desc') }}
        </x-page-desc>
    </div>

	<x-info.laravel-notice></x-info.laravel-notice>

	<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
		@foreach (config('tools') as $toolData)
			@include('admin::lab.parts.tool-item', [
				'toolData' => $toolData
			])
		@endforeach
	</div>
@endsection