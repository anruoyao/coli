@extends('businessLayout::index')

@section('pageContent')
    <div class="mb-6">
        <x-page-title titleText="{{ __('business/jobs.create_title') }}"></x-page-title>
    </div>
    <x-content>
        @livewire('business.jobs.upsert', [
            'jobData' => $jobData,
            'upsertType' => 'create'
        ])
    </x-content>
@endsection
