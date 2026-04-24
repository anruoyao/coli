@extends('businessLayout::index')

@section('pageContent')
	<div class="mb-6">
		<x-page-title titleText="{{ __('business/jobs.edit_title') }}"></x-page-title>
	</div>
	<x-content>
		@livewire('business.jobs.upsert', [
			'jobData' => $jobData,
			'upsertType' => 'edit'
		])
	</x-content>
@endsection
