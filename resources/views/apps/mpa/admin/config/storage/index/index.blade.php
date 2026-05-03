@extends('adminLayout::index')

@section('pageContent')
	<div class="mb-4">
		<x-page-title titleText=" {{ __('admin/storage.index_title') }}"></x-page-title>
		<x-page-desc>
			{{ __('admin/storage.index_desc') }}
		</x-page-desc>
	</div>

	<x-sided-content>
		<x-slot:sideContent>
			<x-entity.previews.round-robin></x-entity.previews.round-robin>
		</x-slot:sideContent>
		
		<div class="flex flex-col gap-4">
			@foreach($roundRobinDisks as $diskData)
				@include('admin::config.storage.index.parts.disk-item', [
					'diskData' => $diskData
				])
				@include('admin::config.storage.index.parts.disk-item', [
					'diskData' => $diskData
				])
				@include('admin::config.storage.index.parts.disk-item', [
					'diskData' => $diskData
				])
				@include('admin::config.storage.index.parts.disk-item', [
					'diskData' => $diskData
				])
				@include('admin::config.storage.index.parts.disk-item', [
					'diskData' => $diskData
				])
			@endforeach
		</div>
	</x-sided-content>
@endsection