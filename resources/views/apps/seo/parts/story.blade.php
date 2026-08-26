@php($story = $data['story'])

<h1>{{ $story->user->name }} · {{ __('seo.story.title_suffix') }}</h1>
<p class="meta-line">
    <a href="{{ $story->user->profile_url }}">{{ $story->user->name }} ({{ $story->user->username }})</a>
</p>

@if($data['image'])
    <img class="preview" src="{{ $data['image'] }}" alt="{{ $story->user->name }}">
@endif

<p class="bio">{{ __('seo.story.expire_hint') }}</p>