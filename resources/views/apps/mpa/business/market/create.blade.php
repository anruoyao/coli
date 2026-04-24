@extends('businessLayout::index')

@section('pageContent')
    <div class="mb-4">
        <x-page-title titleText="{{ __('business/market.create_title') }}"></x-page-title>
    </div>
    <x-content>
        @livewire('business.market.upsert', [
            'productData' => $productData,
            'upsertType' => 'create'
        ])
    </x-content>
@endsection