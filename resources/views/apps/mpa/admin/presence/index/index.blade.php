@extends('adminLayout::index')

@section('headerButtons')
    @if($view === 'live')
        <x-header-btn link="{{ route('admin.presence.export') }}" btnText="{{ __('admin/presence.actions.export') }}" iconName="download-01" iconType="line"></x-header-btn>
    @endif
@endsection

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/presence.index_title') }}"></x-page-title>

    <div class="mb-4">
        <x-tabs.tabs>
            <x-tabs.tab-item :active="$view === 'live'" href="{{ route('admin.presence.index') }}" textLabel="{{ __('admin/presence.tabs.live') }}"></x-tabs.tab-item>
            <x-tabs.tab-item :active="$view === 'analytics'" href="{{ route('admin.presence.index', ['view' => 'analytics']) }}" textLabel="{{ __('admin/presence.tabs.analytics') }}"></x-tabs.tab-item>
        </x-tabs.tabs>
    </div>

    @if($view === 'analytics')
        @include('admin::presence.index.parts.analytics', ['stats' => $stats])
    @else
        <x-content>
            @livewire('admin.presence.online-list')
        </x-content>
    @endif
@endsection