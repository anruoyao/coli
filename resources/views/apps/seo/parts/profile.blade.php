@php($user = $data['user'])

@if($user->avatar_url)
    <img class="avatar" src="{{ $user->avatar_url }}" alt="{{ $user->name }}">
@endif

<h1>{{ $user->name }}</h1>
<p class="meta-line">{{ $user->username }}</p>

<div class="bio">{!! nl2br(e($user->bio ?: __('seo.profile.no_bio'))) !!}</div>

@if($data['posts']->isNotEmpty())
    <h2>{{ __('seo.profile.recent_posts') }}</h2>

    @foreach($data['posts'] as $post)
        <article>
            <div class="author">{{ $user->name }}</div>
            <div class="time">{{ $post->created_at?->getFormatted() }}</div>
            <p>{{ Str::limit($post->content, 400) }}</p>
            <a href="{{ $post->url }}">{{ __('seo.buttons.read_more') }}</a>
        </article>
    @endforeach
@endif