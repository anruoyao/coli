<div wire:poll.30s>
    {{-- 分平台在线统计（在线总数 + Web / Android / iOS） --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        @php($stats = [
            'total'   => __('admin/presence.stats.total'),
            'web'     => __('admin/presence.stats.web'),
            'android' => __('admin/presence.stats.android'),
            'ios'     => __('admin/presence.stats.ios'),
        ])
        @foreach ($stats as $key => $label)
            <div class="bg-bg-pr rounded-2xl p-4 border border-bord-sc">
                <span class="text-par-s block text-lab-sc">{{ $label }}</span>
                <span class="text-title-3 block text-lab-pr2 font-bold font-outfit tracking-tight mt-1">{{ $counts[$key] }}</span>
            </div>
        @endforeach
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-tabs.tabs>
            <x-tabs.tab-item :active="$platform == 'all'" wire:click.prevent="applyPlatform('all')" href="{{ route('admin.presence.index') }}" textLabel="{{ __('admin/presence.tabs.all') }}"/>
            <x-tabs.tab-item :active="$platform == 'web'" wire:click.prevent="applyPlatform('web')" href="{{ route('admin.presence.index') }}" textLabel="{{ __('admin/presence.tabs.web') }}"/>
            <x-tabs.tab-item :active="$platform == 'android'" wire:click.prevent="applyPlatform('android')" href="{{ route('admin.presence.index') }}" textLabel="{{ __('admin/presence.tabs.android') }}"/>
            <x-tabs.tab-item :active="$platform == 'ios'" wire:click.prevent="applyPlatform('ios')" href="{{ route('admin.presence.index') }}" textLabel="{{ __('admin/presence.tabs.ios') }}"/>
        </x-tabs.tabs>

        <div class="flex items-center gap-2">
            <div class="w-64">
                <x-form.text-input
                    inputType="search"
                    name="search"
                    wire:model.live.debounce.500ms="search"
                    :placeholder="__('admin/presence.search_placeholder')">
                </x-form.text-input>
            </div>
            <x-ui.buttons.pill size="sm" variant="outline" btnText="{{ __('admin/presence.actions.refresh') }}" wire:click="refresh"></x-ui.buttons.pill>
        </div>
    </div>

    <x-table.table>
        <x-slot:filter>
            <div class="mb-4">
                <p class="text-par-s text-lab-sc">{{ __('admin/presence.list_helper') }}</p>
            </div>
        </x-slot:filter>
        <x-table.thead>
            <x-table.th>{{ __('admin/presence.table.user') }}</x-table.th>
            <x-table.th>{{ __('admin/presence.table.platform') }}</x-table.th>
            <x-table.th>{{ __('admin/presence.table.origin') }}</x-table.th>
            <x-table.th>{{ __('admin/presence.table.online_since') }}</x-table.th>
            <x-table.th>{{ __('admin/presence.table.last_seen') }}</x-table.th>
            <x-table.th>{{ __('labels.table.actions') }}</x-table.th>
        </x-table.thead>
        <x-table.tbody>
            @forelse($sessions as $session)
                @php($platform = $session->platform ?? 'web')
                @php($badge = $platform === 'android' ? 'success' : ($platform === 'ios' ? 'warning' : 'default'))
                <x-table.tr>
                    <x-table.td variant="strong" weight="medium">
                        <x-table.avatar :avatarSrc="$session->user?->avatar_url"
                            :name="$session->user?->name ?: '#' . $session->user_id"
                            :link="route('admin.users.show', $session->user_id)"/>
                    </x-table.td>
                    <x-table.td>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :variant="$badge">
                                {{ \App\Models\PresenceSession::PLATFORM[$platform] ?? ucfirst($platform) }}
                            </x-badge>
                            @if($session->platform_detail)
                                <span class="text-par-s text-lab-sc">{{ $session->platform_detail }}</span>
                            @endif
                        </div>
                    </x-table.td>
                    <x-table.td variant="muted">
                        @if($session->ip_address)
                            <span class="font-mono text-par-s">{{ $session->ip_address }}</span>
                            @if($session->city || $session->country)
                                <span class="text-par-s text-lab-sc">
                                    {{ trim(($session->city ?? '') . ' / ' . ($session->country ?? ''), ' /') }}
                                </span>
                            @endif
                        @else
                            <x-table.empty-cell></x-table.empty-cell>
                        @endif
                    </x-table.td>
                    <x-table.td variant="muted">
                        @if($session->started_at)
                            {{ $session->started_at->diffForHumans(parts: 2, short: true) }}
                        @else
                            <x-table.empty-cell></x-table.empty-cell>
                        @endif
                    </x-table.td>
                    <x-table.td variant="muted">
                        {{ $session->last_seen_at?->diffForHumans() }}
                    </x-table.td>
                    <x-table.td>
                        <a href="{{ route('admin.users.show', $session->user_id) }}"
                           class="inline-flex items-center gap-1 text-brand-900 hover:underline text-par-s">
                            {{ __('admin/presence.actions.view') }}
                            <span class="size-icon-small shrink-0">
                                <x-ui-icon name="arrow-up-right" type="line"></x-ui-icon>
                            </span>
                        </a>
                    </x-table.td>
                </x-table.tr>
            @empty
                <x-table.empty colspan="6"></x-table.empty>
            @endforelse
        </x-table.tbody>
    </x-table.table>

    @unless($sessions->isEmpty())
        <div class="mt-4">
            {{ $sessions->onEachSide(1)->links('pagination.index') }}
        </div>
    @endunless
</div>