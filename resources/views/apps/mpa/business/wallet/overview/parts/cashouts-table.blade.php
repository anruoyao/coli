<h3 class="text-par-m font-bold text-lab-pr2 mb-4">
    {{ __('business/wallet.cashouts_title') }}
</h3>
<x-table.table>
    <x-table.thead>
        <x-table.th>
            {{ __('table.labels.amount') }}
        </x-table.th>
        <x-table.th>
            {{ __('table.labels.method') }}
        </x-table.th>
        <x-table.th>
            {{ __('table.labels.status') }}
        </x-table.th>
        <x-table.th>
            {{ __('table.labels.commission') }}
        </x-table.th>
        <x-table.th>
            {{ __('table.labels.created_at') }}
        </x-table.th>
        <x-table.th>
            {{ __('table.labels.request_code') }}
        </x-table.th>
    </x-table.thead>
    <x-table.tbody>
        @if($cashouts->isNotEmpty())
            @foreach ($cashouts as $cashout)
                <x-table.tr>
                    <x-table.td variant="strong" weight="medium">
                        {{ $cashout->formatted_amount }}
                    </x-table.td>
                    <x-table.td>
                        {{ $cashout->payment_method }}
                    </x-table.td>

                    <x-table.td bgFill="bg-fill-pr">
                        <x-badge variant="{{ $cashout->status->badgeVariant() }}">
                            {{ $cashout->status->label() }}
                        </x-badge>
                    </x-table.td>

                    <x-table.td variant="muted">
                        {{ $cashout->formatted_commission_fee }}
                    </x-table.td>
                    <x-table.td variant="muted">
                        {{ $cashout->created_at->getFormatted() }}
                    </x-table.td>
                    <x-table.td variant="strong" weight="medium">
                        {{ $cashout->request_code }}
                    </x-table.td>
                </x-table.tr>
            @endforeach
        @else
            <x-table.empty colspan="7"></x-table.empty>
        @endif
    </x-table.tbody>
</x-table.table>

@unless($cashouts->isEmpty())
    <div class="mt-4">
        {{ $cashouts->onEachSide(1)->withQueryString()->links('pagination.index') }}
    </div>
@endif
