
@extends('adminLayout::index')

@section('pageContent')
	<div class="mb-8">
		<x-page-title titleText=" {{ $upsertType == 'create' ? __('admin/categories.create_title') : __('admin/categories.edit_title') }}"></x-page-title>
		<x-page-desc>
			{{ $upsertType == 'create' ? __('admin/categories.create_desc') : __('admin/categories.edit_desc') }}
		</x-page-desc>
	</div>

	<x-content>
		@include('admin::categories.upsert.parts.form')
	</x-content>
@endsection