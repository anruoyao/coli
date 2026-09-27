@extends('emails.layouts.main')

@section('email_content')
    @php
        $zh = str_starts_with($locale, 'zh');
        $btnText = $zh ? '查看详情' : 'View details';
        $settingsUrl = url('/settings/email-notifications');
    @endphp

    <x-emails.title>
        <b>{{ $campaignTitle }}</b>
    </x-emails.title>

    <x-emails.spacer space="12"></x-emails.spacer>
    <x-emails.par>
        {{ $zh ? "嗨，我们有一些关于 {$appName} 的消息想跟你分享：" : "Hi, we have some news from {$appName} to share with you:" }}
    </x-emails.par>

    <x-emails.spacer space="12"></x-emails.spacer>
    <x-emails.par>
        {!! nl2br(e($campaignContent)) !!}
    </x-emails.par>

    @if($destinationUrl)
        <x-emails.spacer space="24"></x-emails.spacer>
        <x-emails.par>
            <div style="text-align: center;">
                <x-emails.action :href="$destinationUrl">
                    {{ $btnText }}
                </x-emails.action>
            </div>
        </x-emails.par>
    @endif

    <x-emails.spacer space="24"></x-emails.spacer>
    <x-emails.par>
        {{ $zh ? '如果不想再收到此类邮件，可在通知设置的「平台通知」中关闭。' : "If you no longer wish to receive these emails, you can turn off \"Platform notifications\" in your notification settings." }}
        <br>
        <a href="{{ $settingsUrl }}" style="color: #333333; text-decoration: underline; font-size: 12px;">
            {{ $zh ? '前往通知设置' : 'Go to notification settings' }}
        </a>
    </x-emails.par>
@endsection