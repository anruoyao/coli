<div wire:poll.30s>
    {{-- 今日 429 统计卡片 --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        @php($statItems = [
            'total' => __('admin/throttle.stats.total'),
            'user'  => __('admin/throttle.stats.user'),
            'ip'    => __('admin/throttle.stats.ip'),
            'gate'  => __('admin/throttle.stats.gate'),
        ])
        @foreach ($statItems as $key => $label)
            <div class="bg-bg-pr rounded-2xl p-4 border border-bord-sc">
                <span class="text-par-s block text-lab-sc">{{ $label }}</span>
                <span class="text-title-3 block text-lab-pr2 font-bold font-outfit tracking-tight mt-1">{{ $stats[$key] }}</span>
            </div>
        @endforeach
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            {{-- 维度筛选 Tab --}}
            <x-tabs.tabs>
                @php($dimensions = [
                    'all'    => __('admin/throttle.tabs.all'),
                    'user'   => __('admin/throttle.tabs.user'),
                    'ip'     => __('admin/throttle.tabs.ip'),
                    'device' => __('admin/throttle.tabs.device'),
                    'global' => __('admin/throttle.tabs.global'),
                ])
                @foreach ($dimensions as $key => $label)
                    <x-tabs.tab-item
                        :active="$dimension == $key"
                        wire:click.prevent="applyDimension('{{ $key }}')"
                        href="{{ route('admin.throttle.index') }}"
                        textLabel="{{ $label }}"/>
                @endforeach
            </x-tabs.tabs>

            {{-- 时间范围 Tab --}}
            <x-tabs.tabs>
                @php($ranges = [
                    'today' => __('admin/throttle.tabs.today'),
                    '24h'   => __('admin/throttle.tabs.24h'),
                    '7d'    => __('admin/throttle.tabs.7d'),
                ])
                @foreach ($ranges as $key => $label)
                    <x-tabs.tab-item
                        :active="$range == $key"
                        wire:click.prevent="applyRange('{{ $key }}')"
                        href="{{ route('admin.throttle.index') }}"
                        textLabel="{{ $label }}"/>
                @endforeach
            </x-tabs.tabs>

            {{-- 分类下拉（近 7 天出现过的防线/分类） --}}
            <div class="w-56">
                <select wire:model.live="category"
                        class="w-full bg-bg-pr border border-bord-sc rounded-lg text-par-s px-3 py-2">
                    <option value="">{{ __('admin/throttle.filter.all_categories') }}</option>
                    @foreach ($categories as $item)
                        <option value="{{ $item }}">{{ $item }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="w-64">
            <x-form.text-input
                inputType="search"
                name="search"
                wire:model.live.debounce.500ms="search"
                :placeholder="__('admin/throttle.search_placeholder')">
            </x-form.text-input>
        </div>
    </div>

    <x-table.table>
        <x-slot:filter>
            <div class="mb-4">
                <p class="text-par-s text-lab-sc">{{ __('admin/throttle.list_helper') }}</p>
            </div>
        </x-slot:filter>
        <x-table.thead>
            <x-table.th>{{ __('admin/throttle.table.time') }}</x-table.th>
            <x-table.th>{{ __('admin/throttle.table.dimension') }}</x-table.th>
            <x-table.th>{{ __('admin/throttle.table.identifier') }}</x-table.th>
            <x-table.th>{{ __('admin/throttle.table.category') }}</x-table.th>
            <x-table.th>{{ __('admin/throttle.table.path') }}</x-table.th>
            <x-table.th>{{ __('admin/throttle.table.ip') }}</x-table.th>
        </x-table.thead>
        <x-table.tbody>
            @forelse($events as $event)
                <x-table.tr>
                    <x-table.td variant="muted">
                        {{ $event->created_at?->format('Y-m-d H:i:s') }}
                    </x-table.td>
                    <x-table.td>
                        @php($badge = match($event->dimension) {
                            'user' => 'default',
                            'ip' => 'warning',
                            'device' => 'success',
                            default => 'danger',
                        })
                        <x-badge :variant="$badge">
                            {{ strtoupper($event->dimension) }}
                        </x-badge>
                    </x-table.td>
                    <x-table.td>
                        <span class="font-mono text-par-s">{{ $event->dimension === 'user' ? '#'.$event->identifier : $event->identifier }}</span>
                    </x-table.td>
                    <x-table.td>
                        <span class="font-mono text-par-s">{{ $event->category ?? '-' }}</span>
                        @if($event->limit)
                            <span class="text-par-s text-lab-sc">({{ $event->limit }} / {{ $event->window_seconds }}s)</span>
                        @endif
                    </x-table.td>
                    <x-table.td variant="muted">
                        <span class="font-mono text-par-s">{{ $event->method }} {{ $event->path ?? '-' }}</span>
                    </x-table.td>
                    <x-table.td variant="muted">
                        <span class="font-mono text-par-s">{{ $event->ip_address ?? '-' }}</span>
                    </x-table.td>
                </x-table.tr>
            @empty
                <x-table.empty colspan="6"></x-table.empty>
            @endforelse
        </x-table.tbody>
    </x-table.table>

    @unless($events->isEmpty())
        <div class="mt-4">
            {{ $events->onEachSide(1)->links('pagination.index') }}
        </div>
    @endunless
</div>
