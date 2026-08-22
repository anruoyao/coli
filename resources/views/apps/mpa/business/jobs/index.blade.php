@extends('businessLayout::index')

@section('pageContent')
    <div class="mb-6">
        <x-page-title titleText="{{ __('business/jobs.index_title') }}"></x-page-title>
        <x-tabs.tabs>
            <x-tabs.tab-item :active="$type == 'all'" href="{{ route('business.jobs.index', ['type' => 'all']) }}" textLabel="{{ __('business/jobs.tabs.all') }}"></x-tabs.tab-item>
            <x-tabs.tab-item :active="$type == 'active'" href="{{ route('business.jobs.index', ['type' => 'active']) }}" textLabel="{{ __('business/jobs.tabs.active') }}"></x-tabs.tab-item>
            <x-tabs.tab-item :active="$type == 'archived'" href="{{ route('business.jobs.index', ['type' => 'archived']) }}" textLabel="{{ __('business/jobs.tabs.archived') }}"></x-tabs.tab-item>
        </x-tabs.tabs>
    </div>
    @if($jobsList->isNotEmpty())
        <div class="grid grid-cols-2 gap-4">
            @foreach ($jobsList as $jobData)
                @include('business::jobs.parts.index.job-card', [
                    'jobData' => $jobData
                ])
            @endforeach
        </div>
    @else
        @if($type == 'all')
            <x-page-states.empty
                title="{{ __('business/jobs.empty_state.index_all.title') }}"
            desc="{{ __('business/jobs.empty_state.index_all.desc') }}"></x-page-states.empty>
        @elseif($type == 'active')
            <x-page-states.empty
                title="{{ __('business/jobs.empty_state.index_active.title') }}"
            desc="{{ __('business/jobs.empty_state.index_active.desc') }}"></x-page-states.empty>
        @else
            <x-page-states.empty
                title="{{ __('business/jobs.empty_state.index_archived.title') }}"
            desc="{{ __('business/jobs.empty_state.index_archived.desc') }}"></x-page-states.empty>
        @endif
    @endif

    @unless($jobsList->isEmpty())
        <div class="mt-4">
            {{ $jobsList->onEachSide(1)->withQueryString()->links('pagination.index') }}
        </div>
    @endif
@endsection
