@extends('emails.layouts.main')

@section('email_content')
    @php
        $zh = $payload['is_zh'];
        $btnText = $zh ? '在平台上查看' : 'View on the platform';
    @endphp

    <x-emails.title>
        {{ $zh ? '互动摘要' : 'Interaction digest' }}
    </x-emails.title>

    <x-emails.spacer space="12"></x-emails.spacer>
    <x-emails.par>
        {{ $zh ? "嗨，{$user->name}，过去 3 小时内你的内容收到了新的互动：" : "Hi {$user->name}, here is what happened to your content in the past 3 hours:" }}
    </x-emails.par>

    <x-emails.spacer space="12"></x-emails.spacer>

    {{-- 新粉丝 --}}
    @if(count($payload['followers']) > 0)
        <x-emails.par>
            <b>{{ $zh ? '新粉丝' : 'New followers' }}（{{ count($payload['followers']) + $payload['extra_followers'] }}）</b>
        </x-emails.par>
        <x-emails.spacer space="6"></x-emails.spacer>
        @foreach($payload['followers'] as $follower)
            <x-emails.par>
                <b>{{ $follower['actor_name'] }}</b> {{ $zh ? '关注了你' : 'followed you' }}
            </x-emails.par>
        @endforeach
        @if($payload['extra_followers'] > 0)
            <x-emails.par>
                {{ $zh ? "以及其他 {$payload['extra_followers']} 位新粉丝" : "and {$payload['extra_followers']} other new follower(s)" }}
            </x-emails.par>
        @endif
        <x-emails.spacer space="12"></x-emails.spacer>
    @endif

    {{-- 帖子/评论/故事区块 --}}
    @foreach($payload['sections'] as $section)
        <x-emails.par>
            <b>「{{ $section['title'] }}」</b>
        </x-emails.par>
        <x-emails.spacer space="6"></x-emails.spacer>

        @if(count($section['reactions']) > 0)
            <x-emails.par>
                <span style="font-size: 12px; color: #777;">{{ $zh ? '互动' : 'Reactions' }}（{{ count($section['reactions']) + $section['extra_reactions'] }}）</span>
            </x-emails.par>
            @foreach($section['reactions'] as $reaction)
                <x-emails.par>
                    <b>{{ $reaction['actor_name'] }}</b>
                    <span>{{ $zh ? '对此内容发出了' : 'reacted with' }}</span>
                    @if(! empty($reaction['reaction_img']))
                        <img style="width: 18px; height: 18px; vertical-align: middle; display: inline;" src="{{ $reaction['reaction_img'] }}" alt="Reaction">
                    @endif
                </x-emails.par>
            @endforeach
            @if($section['extra_reactions'] > 0)
                <x-emails.par>
                    {{ $zh ? "以及其他 {$section['extra_reactions']} 人" : "and {$section['extra_reactions']} other(s)" }}
                </x-emails.par>
            @endif
            <x-emails.spacer space="6"></x-emails.spacer>
        @endif

        @if(count($section['comments']) > 0)
            <x-emails.par>
                <span style="font-size: 12px; color: #777;">{{ $zh ? '评论' : 'Comments' }}（{{ count($section['comments']) + $section['extra_comments'] }}）</span>
            </x-emails.par>
            @foreach($section['comments'] as $comment)
                <x-emails.par>
                    <b>{{ $comment['actor_name'] }}</b>：{{ $comment['content'] }}
                </x-emails.par>
            @endforeach
            @if($section['extra_comments'] > 0)
                <x-emails.par>
                    {{ $zh ? "以及其他 {$section['extra_comments']} 条评论" : "and {$section['extra_comments']} other comment(s)" }}
                </x-emails.par>
            @endif
            <x-emails.spacer space="6"></x-emails.spacer>
        @endif

        @if(count($section['mentions']) > 0)
            <x-emails.par>
                <span style="font-size: 12px; color: #777;">{{ $zh ? '@提及' : 'Mentions' }}（{{ count($section['mentions']) }}）</span>
            </x-emails.par>
            @foreach($section['mentions'] as $mention)
                <x-emails.par>
                    <b>{{ $mention['actor_name'] }}</b> {{ $zh ? '提到了你' : 'mentioned you' }}
                </x-emails.par>
            @endforeach
            <x-emails.spacer space="6"></x-emails.spacer>
        @endif

        <x-emails.spacer space="12"></x-emails.spacer>
    @endforeach

    @if($payload['extra_entities'] > 0)
        <x-emails.par>
            {{ $zh ? "以及其他 {$payload['extra_entities']} 篇帖子/内容也收到了互动" : "and {$payload['extra_entities']} more posts/content also got interactions" }}
        </x-emails.par>
        <x-emails.spacer space="12"></x-emails.spacer>
    @endif

    <x-emails.spacer space="24"></x-emails.spacer>
    <x-emails.par>
        <div style="text-align: center;">
            <x-emails.action :href="url('/')">
                {{ $btnText }}
            </x-emails.action>
        </div>
    </x-emails.par>

    <x-emails.spacer space="12"></x-emails.spacer>
    <x-emails.par>
        {{ $zh ? '如果不想收到此类邮件，请在通知设置中调整偏好。' : 'You can manage these emails in your notification settings.' }}
    </x-emails.par>
@endsection