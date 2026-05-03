@extends('businessLayout::index')

@section('pageContent')
    <div class="mb-6">
        <div class="mb-8">
            <x-page-title titleText="{{ __('business/market.index_title') }}"></x-page-title>
            <x-page-desc>
                {{ __('business/market.index_desc') }} <a href="{{ asset(config('marketplace.document_links.trade_guide')) }}" target="_blank" class="text-brand-900 underline">{{ __('labels.learn_more') }} &raquo;</a>
            </x-page-desc>
        </div>
        <x-tabs.tabs>
            <x-tabs.tab-item :active="$type == 'all'" href="{{ route('business.market.index', ['type' => 'all']) }}" textLabel="{{ __('business/market.tabs.all') }}"></x-tabs.tab-item>
            <x-tabs.tab-item :active="$type == 'active'" href="{{ route('business.market.index', ['type' => 'active']) }}" textLabel="{{ __('business/market.tabs.active') }}"></x-tabs.tab-item>
            <x-tabs.tab-item :active="$type == 'archived'" href="{{ route('business.market.index', ['type' => 'archived']) }}" textLabel="{{ __('business/market.tabs.archived') }}"></x-tabs.tab-item>
        </x-tabs.tabs>
    </div>
    <x-table.table>
        <x-table.thead>
            <x-table.th>
                {{ __('business/table.th.name') }}
            </x-table.th>
            <x-table.th>
                {{ __('business/table.th.quantity') }}
            </x-table.th>
            <x-table.th>
                {{ __('business/table.th.price') }}
            </x-table.th>
            <x-table.th>
                {{ __('business/table.th.status') }}
            </x-table.th>
            <x-table.th>
                {{ __('business/table.th.category') }}
            </x-table.th>
            <x-table.th>
                {{ __('business/table.th.product_type') }}
            </x-table.th>
            <x-table.th classes="text-right">
                {{ __('labels.table.actions') }}
            </x-table.th>
        </x-table.thead>
        <x-table.tbody>
            @if($productsList->isNotEmpty())
                @foreach ($productsList as $productData)
                    @include('business::market.index.parts.product-item', ['productData' => $productData])
                @endforeach
            @else
                <x-table.empty colspan="7"></x-table.empty>
            @endif
        </x-table.tbody>
    </x-table.table>

    @unless($productsList->isEmpty())
        <div class="mt-4">
            {{ $productsList->onEachSide(1)->withQueryString()->links('pagination.index') }}
        </div>
    @endif
@endsection