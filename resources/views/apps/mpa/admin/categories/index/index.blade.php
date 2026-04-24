@extends('adminLayout::index')

@section('headerButtons')
	<x-header-btn link="{{ route('admin.categories.create') }}" btnText="{{ __('admin/categories.create_category') }}" iconName="plus" iconType="solid"></x-header-btn>
@endsection

@section('pageContent')
	<div class="mb-8">
        <x-page-title titleText=" {{ __('admin/categories.index_title') }}"></x-page-title>
        <x-page-desc>
            {{ __('admin/categories.index_desc') }}
        </x-page-desc>
    </div>

	<div class="mb-4">
		<x-tabs.tabs>
			<x-tabs.tab-item :active="$filters['type'] == 'all'" href="{{ route('admin.categories.index') }}" textLabel="{{ __('admin/filter.filters.categories.tabs.all') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['type'] == 'product'" href="{{ route('admin.categories.index', ['type' => 'product']) }}" textLabel="{{ __('admin/filter.filters.categories.tabs.product') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['type'] == 'job'" href="{{ route('admin.categories.index', ['type' => 'job']) }}" textLabel="{{ __('admin/filter.filters.categories.tabs.job') }}"></x-tabs.tab-item>
		</x-tabs.tabs>
	</div>

	<x-table.table>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.name') }}</x-table.th>
			<x-table.th>{{ __('table.labels.type') }}</x-table.th>
			<x-table.th>{{ __('table.labels.usage') }}</x-table.th>
			<x-table.th>{{ __('table.labels.depth') }}</x-table.th>
			<x-table.th>#ID</x-table.th>
			<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($categories->isNotEmpty())
				@foreach ($categories as $categoryData)
					@include('admin::categories.index.parts.category-item', [
						'categoryData' => $categoryData
					])
				@endforeach
			@else
				<x-table.empty colspan="9"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

	@unless($categories->isEmpty())
		<div class="mt-4">
			{{ $categories->onEachSide(1)->withQueryString()->links('pagination.index') }}
		</div>
	@endunless
@endsection