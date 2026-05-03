@extends('businessLayout::index')

@section('pageContent')
    <div class="mb-6">
        <x-page-title titleText="{{ __('business/ads.edit_title') }}"></x-page-title>
    </div>
    <x-content>
        @livewire('business.ads.upsert', [
            'adData' => $adData,
            'upsertType' => 'edit'
        ])
    </x-content>
@endsection