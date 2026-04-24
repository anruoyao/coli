<x-table.tr>
	<x-table.td variant="strong" weight="medium">
		<x-table.avatar :avatarSrc="$productData->user->avatar_url" :name="$productData->user->name" :link="route('admin.users.show', $productData->user->id)" />
	</x-table.td>
	<x-table.td variant="strong" weight="medium">
		<a href="{{ route('admin.market.show', $productData->id) }}" class="hover:underline whitespace-nowrap">
			{!! truncate_text($productData->title, 22) !!}
		</a>
	</x-table.td>
	<x-table.td variant="muted">
		{{ $productData->category_name }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $productData->approval->label() }} {{ $productData->approval->emoji() }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $productData->status->label() }} {{ $productData->status->emoji() }}
	</x-table.td>
	<x-table.td variant="money" weight="medium">
		{{ $productData->formatted_price }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $productData->created_at->getDate() }}
	</x-table.td>
	<x-table.td variant="muted" numeric>
		{{ $productData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.market.show', $productData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>