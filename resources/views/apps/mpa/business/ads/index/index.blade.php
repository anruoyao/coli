@extends('businessLayout::index')

@section('pageContent')
    <div class="mb-6">
        <x-page-title titleText="{{ __('business/ads.index_title') }}"></x-page-title>
        <x-tabs.tabs>
            <x-tabs.tab-item :active="$type == 'all'" href="{{ route('business.ads.index', ['type' => 'all']) }}" textLabel="{{ __('business/ads.tabs.all') }}"></x-tabs.tab-item>
            <x-tabs.tab-item :active="$type == 'active'" href="{{ route('business.ads.index', ['type' => 'active']) }}" textLabel="{{ __('business/ads.tabs.active') }}"></x-tabs.tab-item>
            <x-tabs.tab-item :active="$type == 'archived'" href="{{ route('business.ads.index', ['type' => 'archived']) }}" textLabel="{{ __('business/ads.tabs.archived') }}"></x-tabs.tab-item>
        </x-tabs.tabs>
    </div>

    @if($adsList->isNotEmpty())
        <div class="grid grid-cols-3 gap-4">
            @foreach ($adsList as $adData)
                @include('business::ads.parts.index.ad-card', [
                    'adData' => $adData
                ])
            @endforeach
        </div>
    @else
        @if($type == 'all')
            <x-page-states.empty
                title="{{ __('business/ads.empty_state.index_all.title') }}"
            desc="{{ __('business/ads.empty_state.index_all.desc') }}"></x-page-states.empty>
        @elseif($type == 'active')
            <x-page-states.empty
                title="{{ __('business/ads.empty_state.index_active.title') }}"
            desc="{{ __('business/ads.empty_state.index_active.desc') }}"></x-page-states.empty>
        @else
            <x-page-states.empty
                title="{{ __('business/ads.empty_state.index_archived.title') }}"
            desc="{{ __('business/ads.empty_state.index_archived.desc') }}"></x-page-states.empty>
        @endif
    @endif
    @unless($adsList->isEmpty())
        <div class="mt-4">
            {{ $adsList->onEachSide(1)->withQueryString()->links('pagination.index') }}
        </div>
    @endif
@endsection
