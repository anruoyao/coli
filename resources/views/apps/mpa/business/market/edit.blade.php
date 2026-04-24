@extends('businessLayout::index')

@section('pageContent')
    <div class="mb-4">
        <x-page-title titleText="{{ __('business/market.edit_title') }}"></x-page-title>
    </div>
    <x-content>
        @livewire('business.market.upsert', [
            'productData' => $productData,
            'upsertType' => 'edit'
        ])
    </x-content>
@endsection