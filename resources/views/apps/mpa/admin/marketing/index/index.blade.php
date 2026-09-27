@extends('adminLayout::index')

@section('pageContent')
    <div class="flex items-start justify-between mb-4">
        <x-page-title titleText="{{ __('admin/marketing.index_title') }}"></x-page-title>
        @if(config('notifications.marketing.enabled', false))
            <a href="{{ route('admin.marketing.create') }}" class="inline-block">
                <x-ui.buttons.pill size="md" btnText="{{ __('admin/marketing.create_button') }}"></x-ui.buttons.pill>
            </a>
        @else
            <a href="{{ route('admin.config.notifications') }}" class="inline-block">
                <x-ui.buttons.pill size="md" btnText="{{ __('admin/marketing.module_disabled') }}"></x-ui.buttons.pill>
            </a>
        @endif
    </div>

    @unless(config('notifications.marketing.enabled', false))
        <p class="text-par-m text-lab-sc mb-4">
            {{ __('admin/marketing.module_disabled_helper') }}
        </p>
    @endunless

    <x-table.table>
        <x-table.thead>
            <x-table.th>{{ __('admin/marketing.table.title') }}</x-table.th>
            <x-table.th>{{ __('admin/marketing.table.target_type') }}</x-table.th>
            <x-table.th>{{ __('admin/marketing.table.channels') }}</x-table.th>
            <x-table.th>{{ __('admin/marketing.table.status') }}</x-table.th>
            <x-table.th>{{ __('admin/marketing.table.email_stats') }}</x-table.th>
            <x-table.th>{{ __('admin/marketing.table.in_app_stats') }}</x-table.th>
            <x-table.th>{{ __('admin/marketing.table.created_at') }}</x-table.th>
            <x-table.th></x-table.th>
        </x-table.thead>
        <x-table.tbody>
            @if($campaigns->isNotEmpty())
                @foreach ($campaigns as $campaign)
                    <x-table.tr>
                        <x-table.td variant="strong" weight="medium">
                            {{ $campaign->title }}
                        </x-table.td>
                        <x-table.td variant="muted">
                            {{ __('admin/marketing.targets.' . $campaign->target_type) }}
                        </x-table.td>
                        <x-table.td variant="muted">
                            <div class="flex items-center gap-1 flex-wrap">
                                @if($campaign->email_enabled)
                                    <span class="text-par-s text-lab-sc bg-fill-qt rounded-full px-2 py-1">{{ __('admin/marketing.channels.email') }}</span>
                                @endif
                                @if($campaign->in_app_enabled)
                                    <span class="text-par-s text-lab-sc bg-fill-qt rounded-full px-2 py-1">{{ __('admin/marketing.channels.in_app') }}</span>
                                @endif
                            </div>
                        </x-table.td>
                        <x-table.td variant="muted">
                            <span class="font-medium">{{ __('admin/marketing.statuses.' . $campaign->status) }}</span>
                        </x-table.td>
                        <x-table.td variant="muted" :numeric="true">
                            {{ $campaign->email_sent_count }}/{{ $campaign->email_recipient_count }}
                        </x-table.td>
                        <x-table.td variant="muted" :numeric="true">
                            {{ $campaign->in_app_sent_count }}/{{ $campaign->in_app_recipient_count }}
                        </x-table.td>
                        <x-table.td variant="muted">
                            {{ $campaign->created_at?->getFormatted() }}
                        </x-table.td>
                        <x-table.td>
                            <div class="flex justify-end">
                                <a href="{{ route('admin.marketing.show', $campaign->id) }}">
                                    <x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
                                </a>
                            </div>
                        </x-table.td>
                    </x-table.tr>
                @endforeach
            @else
                <x-table.empty colspan="8"></x-table.empty>
            @endif
        </x-table.tbody>
    </x-table.table>

    @unless($campaigns->isEmpty())
        <div class="mt-4">
            {{ $campaigns->onEachSide(1)->withQueryString()->links('pagination.index') }}
        </div>
    @endunless
@endsection