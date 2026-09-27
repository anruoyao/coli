@extends('adminLayout::index')

@section('pageContent')
	<x-page-title titleText=" {{ __('admin/config.notifications_settings') }}"></x-page-title>

	<x-sided-content>
		<x-slot:sideContent>
			<x-config.readonly-notice></x-config.readonly-notice>
		</x-slot:sideContent>

		<div class="flex flex-col gap-6">
			<x-config.env
				name="NOTIFICATIONS_EMAIL_ENABLED"
				description="{{ __('admin/config.captions.notifications_email_enabled') }}"
			value="{{ config('notifications.email.enabled') }}"/>

			<x-config.env
				name="NOTIFICATIONS_BROADCAST_ENABLED"
				description="{{ __('admin/config.captions.notifications_broadcast_enabled') }}"
			value="{{ config('notifications.broadcast.enabled') }}"/>

			<x-config.env
				name="MARKETING_NOTIFICATIONS_ENABLED"
				description="{{ __('admin/config.captions.marketing_enabled') }}"
			value="{{ config('notifications.marketing.enabled') }}"/>

			<x-config.env
				name="MARKETING_EMAIL_ENABLED"
				description="{{ __('admin/config.captions.marketing_email_enabled') }}"
			value="{{ config('notifications.marketing.email.enabled') }}"/>

			<x-config.env
				name="MARKETING_INAPP_ENABLED"
				description="{{ __('admin/config.captions.marketing_inapp_enabled') }}"
			value="{{ config('notifications.marketing.in_app_enabled') }}"/>

			<x-config.env
				name="MARKETING_EMAIL_INITIAL_PER_MINUTE"
				description="{{ __('admin/config.captions.marketing_email_initial_per_minute') }}"
			value="{{ config('notifications.marketing.email.initial_per_minute') }}"/>

			<x-config.env
				name="MARKETING_EMAIL_MIN_PER_MINUTE"
				description="{{ __('admin/config.captions.marketing_email_min_per_minute') }}"
			value="{{ config('notifications.marketing.email.min_per_minute') }}"/>

			<x-config.env
				name="MARKETING_EMAIL_MAX_PER_MINUTE"
				description="{{ __('admin/config.captions.marketing_email_max_per_minute') }}"
			value="{{ config('notifications.marketing.email.max_per_minute') }}"/>

			<x-config.env
				name="MARKETING_EMAIL_COOLDOWN_SECONDS"
				description="{{ __('admin/config.captions.marketing_email_cooldown') }}"
			value="{{ config('notifications.marketing.email.cooldown_seconds') }}"/>

			<x-config.env
				name="MARKETING_DISPATCH_PER_TICK"
				description="{{ __('admin/config.captions.marketing_dispatch_per_tick') }}"
			value="{{ config('notifications.marketing.dispatch.per_tick') }}"/>

			<x-config.env
				name="MARKETING_CAMPAIGN_SEND_ROOT_ONLY"
				description="{{ __('admin/config.captions.marketing_send_root_only') }}"
			value="{{ config('notifications.marketing.send_root_only') }}"/>
		</div>
	</x-sided-content>
@endsection