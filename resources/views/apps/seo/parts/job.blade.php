@php($job = $data['job'])

<h1>{{ $job->title }}</h1>

<p>
    @if($job->type)
        <span class="pill">{{ $job->type->label() }}</span>
    @endif
    @if($job->is_remote)
        <span class="pill">{{ __('seo.job.remote') }}</span>
    @elseif($job->location)
        <span class="pill">{{ $job->location }}</span>
    @endif
    @if($job->formatted_income)
        <span class="pill">{{ $job->formatted_income }}</span>
    @endif
</p>

<p class="meta-line">
    {{ __('seo.job.by') }}
    <a href="{{ $job->user->profile_url }}">{{ $job->user->name }}</a>
</p>

@if($job->overview)
    <h2>{{ __('seo.job.overview') }}</h2>
    <div class="bio">{!! nl2br(e($job->overview)) !!}</div>
@endif

@if($job->description)
    <h2>{{ __('seo.job.description') }}</h2>
    <div class="bio">{!! nl2br(e($job->description)) !!}</div>
@endif