@php($post = $data['post'])

<article>
    <div class="author">
        <a href="{{ $post->user->profile_url }}">{{ $post->user->name }} ({{ $post->user->username }})</a>
    </div>
    <div class="time">{{ $post->created_at?->getFormatted() }}</div>
    <p>{!! nl2br(e($post->content)) !!}</p>

    @if($data['image'])
        <img class="preview" src="{{ $data['image'] }}" alt="{{ Str::limit($post->content, 80) }}">
    @endif
</article>

<p class="meta-line">
    <a href="{{ $post->user->profile_url }}">{{ __('seo.post.back_to_profile', ['name' => $post->user->name]) }}</a>
</p>