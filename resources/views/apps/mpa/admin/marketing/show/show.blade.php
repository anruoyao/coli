@extends('adminLayout::index')

@section('pageContent')
    <div class="flex items-start justify-between mb-6">
        <x-page-title titleText="{{ $campaign->title }}"></x-page-title>
        <div class="flex items-center gap-3">
            @if($campaign->status === \App\Models\MarketingCampaign::STATUS_DRAFT || $campaign->status === \App\Models\MarketingCampaign::STATUS_CANCELLED)
                <form action="{{ route('admin.marketing.send', $campaign->id) }}" method="POST">
                    @csrf
                    <x-ui.buttons.pill size="md" type="submit" btnText="{{ __('admin/marketing.send_button') }}"></x-ui.buttons.pill>
                </form>
            @endif

            @if($campaign->status === \App\Models\MarketingCampaign::STATUS_SENDING)
                <form action="{{ route('admin.marketing.cancel', $campaign->id) }}" method="POST">
                    @csrf
                    <x-ui.buttons.pill size="md" type="submit" variant="danger" btnText="{{ __('admin/marketing.cancel_button') }}"></x-ui.buttons.pill>
                </form>
            @endif
        </div>
    </div>

    <x-sided-content>
        <x-slot:sideContent>
            <div class="flex flex-col gap-6">
                <div>
                    <h4 class="text-par-m font-medium text-lab-pr2 mb-2">{{ __('admin/marketing.status_label') }}</h4>
                    <p class="text-par-m text-lab-sc">
                        @if($campaign->status === \App\Models\MarketingCampaign::STATUS_SENDING)
                            {{ __('admin/marketing.status_hint_sending') }}
                        @elseif($campaign->status === \App\Models\MarketingCampaign::STATUS_COMPLETED)
                            {{ __('admin/marketing.status_hint_completed') }}
                        @elseif($campaign->status === \App\Models\MarketingCampaign::STATUS_CANCELLED)
                            {{ __('admin/marketing.status_hint_cancelled') }}
                        @else
                            {{ __('admin/marketing.status_hint_draft') }}
                        @endif
                    </p>
                </div>
            </div>
        </x-slot:sideContent>

        <div>
            <div class="rounded-2xl border border-bord-card p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <h4 class="text-par-m text-lab-sc mb-1">{{ __('admin/marketing.form.subject') }}</h4>
                        <p class="text-par-m text-lab-pr">{{ $campaign->subject }}</p>
                    </div>
                    <div>
                        <h4 class="text-par-m text-lab-sc mb-1">{{ __('admin/marketing.form.target_type') }}</h4>
                        <p class="text-par-m text-lab-pr">{{ __('admin/marketing.targets.' . $campaign->target_type) }}</p>
                    </div>
                    <div>
                        <h4 class="text-par-m text-lab-sc mb-1">{{ __('admin/marketing.form.landing_url') }}</h4>
                        <p class="text-par-m text-lab-pr break-all">{{ $campaign->landing_url ?: '-' }}</p>
                    </div>
                </div>

                <h4 class="text-par-m text-lab-sc mb-1">{{ __('admin/marketing.form.content') }}</h4>
                <p class="text-par-m text-lab-pr whitespace-pre-line">{{ $campaign->content }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="rounded-2xl border border-bord-card p-6">
                    <h4 class="text-par-m font-medium text-lab-pr2 mb-4">{{ __('admin/marketing.channels.email') }} ({{ $campaign->email_sent_count }}/{{ $campaign->email_recipient_count }})</h4>
                    <div class="flex flex-col gap-2 text-par-m text-lab-sc">
                        <span>{{ __('admin/marketing.stats.pending') }}: <b class="text-lab-pr">{{ $counts['email_pending_queued'] }}</b></span>
                        <span>{{ __('admin/marketing.stats.sent') }}: <b class="text-lab-pr">{{ $counts['email_sent'] }}</b></span>
                        <span>{{ __('admin/marketing.stats.failed') }}: <b class="text-rose-600">{{ $counts['email_failed'] }}</b></span>
                        <span>{{ __('admin/marketing.stats.skipped') }}: <b class="text-lab-sc">{{ $counts['email_skipped'] }}</b></span>
                    </div>
                </div>

                <div class="rounded-2xl border border-bord-card p-6">
                    <h4 class="text-par-m font-medium text-lab-pr2 mb-4">{{ __('admin/marketing.channels.in_app') }} ({{ $campaign->in_app_sent_count }}/{{ $campaign->in_app_recipient_count }})</h4>
                    <div class="flex flex-col gap-2 text-par-m text-lab-sc">
                        <span>{{ __('admin/marketing.stats.pending') }}: <b class="text-lab-pr">{{ $counts['in_app_pending_queued'] }}</b></span>
                        <span>{{ __('admin/marketing.stats.sent') }}: <b class="text-lab-pr">{{ $counts['in_app_sent'] }}</b></span>
                        <span>{{ __('admin/marketing.stats.failed') }}: <b class="text-rose-600">{{ $counts['in_app_failed'] }}</b></span>
                        <span>{{ __('admin/marketing.stats.skipped') }}: <b class="text-lab-sc">{{ $counts['in_app_skipped'] }}</b></span>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <a href="{{ route('admin.marketing.index') }}" class="text-par-m text-brand-900">
                    &larr; {{ __('admin/marketing.back_to_list') }}
                </a>
            </div>
        </div>
    </x-sided-content>
@endsection