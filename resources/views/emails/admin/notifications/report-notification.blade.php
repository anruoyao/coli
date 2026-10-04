@extends('emails.layouts.main')

@section('email_content')
    <x-emails.title>
        {{ __('admin/notifications.report.title') }} #{{ $report['id'] }}
    </x-emails.title>

    <x-emails.par style="font-weight: bold;">
        {{ __('admin/notifications.report.report_time') }}: {{ $report['time'] }}
    </x-emails.par>
    <x-emails.spacer space="8"></x-emails.spacer>
    <x-emails.par>
        {{ __('admin/notifications.report.reporter') }}: {{ $report['reporter']['username'] }} (ID: {{ $report['reporter']['id'] }})
        <a href="{{ $report['reporter']['url'] }}" style="color: blue; text-decoration: underline;" target="_blank">{{ $report['reporter']['url'] }}</a>
    </x-emails.par>
    @if($report['reporter']['ip'])
        <x-emails.par>
            {{ __('admin/notifications.report.reporter_ip') }}: {{ $report['reporter']['ip'] }}
        </x-emails.par>
    @endif
    <x-emails.spacer space="16"></x-emails.spacer>
    <x-emails.par>
        {{ __('admin/notifications.report.reported_content') }}: <strong>{{ $report['target']['type_label'] }}</strong> (ID: {{ $report['target']['id'] }})
        <a href="{{ $report['target']['url'] }}" style="color: blue; text-decoration: underline;" target="_blank">{{ $report['target']['url'] }}</a>
    </x-emails.par>
    @if($report['target']['preview'])
        <x-emails.par>
            {{ $report['target']['preview'] }}
        </x-emails.par>
    @endif
    <x-emails.spacer space="16"></x-emails.spacer>
    <x-emails.par>
        {{ __('admin/notifications.report.reason') }}: <strong>{{ $report['reason']['title'] }}</strong>
    </x-emails.par>
    @if($report['reason']['description'])
        <x-emails.par>
            {{ $report['reason']['description'] }}
        </x-emails.par>
    @endif
    <x-emails.spacer space="16"></x-emails.spacer>
    <x-emails.par>
        {{ __('admin/notifications.report.comment') }}: {{ $report['comment'] ?: __('admin/notifications.report.no_comment') }}
    </x-emails.par>
    @if($report['media'] !== [])
        <x-emails.spacer space="16"></x-emails.spacer>
        <x-emails.par>
            {{ __('admin/notifications.report.evidence') }}:
        </x-emails.par>
        <x-emails.spacer space="8"></x-emails.spacer>
        @foreach($report['media'] as $mediaItem)
            <p style="margin: 0 0 8px 0;">
                <a href="{{ $mediaItem['source'] }}" target="_blank">
                    <img src="{{ $mediaItem['thumbnail'] }}" alt="evidence" style="display: block; max-width: 120px; max-height: 120px; border: 1px solid #e5e5e5; border-radius: 4px;">
                </a>
            </p>
        @endforeach
    @endif
    <x-emails.spacer space="24"></x-emails.spacer>
    <x-emails.action href="{{ $report['admin_url'] }}">
        {{ __('admin/notifications.report.review_in_admin') }}
    </x-emails.action>
@endsection
