<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo->title }} · {{ config('app.name') }}</title>

    <meta name="description" content="{{ $seo->description }}">
    <link rel="canonical" href="{{ $seo->canonical }}">
    <meta name="robots" content="index, follow, max-image-preview:large">

    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="{{ $seo->ogType() }}">
    <meta property="og:title" content="{{ $seo->title }}">
    <meta property="og:description" content="{{ $seo->description }}">
    <meta property="og:url" content="{{ $seo->canonical }}">
    <meta property="og:image" content="{{ $seo->image ?: asset('assets/logos/light.png') }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">

    @if($seo->type === 'profile')
        <meta property="profile:username" content="{{ $seo->data['user']->username }}">
    @endif

    <meta name="twitter:card" content="{{ $seo->image ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $seo->title }}">
    <meta name="twitter:description" content="{{ $seo->description }}">
    <meta name="twitter:image" content="{{ $seo->image ?: asset('assets/logos/light.png') }}">

    @if(! empty($seo->jsonLd))
        <script type="application/ld+json">{!! json_encode($seo->jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif

    <style>
        :root { --fg: #1a1a2e; --muted: #6b7280; --line: #e5e7eb; --accent: #4f46e5; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", "Microsoft YaHei", sans-serif; color: var(--fg); background: #f9fafb; line-height: 1.6; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 0 20px 64px; }
        header { padding: 20px 0; border-bottom: 1px solid var(--line); margin-bottom: 28px; }
        header a { display: inline-flex; align-items: center; gap: 10px; color: var(--fg); text-decoration: none; font-weight: 600; }
        header img { height: 26px; }
        main h1 { font-size: 26px; margin: 0 0 6px; }
        main h2 { font-size: 19px; margin: 28px 0 10px; }
        .muted { color: var(--muted); }
        .avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; display: block; margin: 0 0 14px; border: 1px solid var(--line); }
        .bio { font-size: 15px; }
        .meta-line { font-size: 14px; color: var(--muted); margin: 4px 0; }
        article { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 16px 18px; margin: 12px 0; }
        article .author { font-weight: 600; margin-bottom: 4px; }
        article .time { font-size: 12px; color: var(--muted); }
        article img.preview { max-width: 100%; max-height: 420px; border-radius: 10px; margin-top: 10px; display: block; }
        .price { font-size: 15px; color: var(--accent); font-weight: 600; }
        footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid var(--line); font-size: 13px; color: var(--muted); }
        .pill { display: inline-block; background: rgba(79,70,229,.08); color: var(--accent); border-radius: 999px; padding: 3px 12px; font-size: 13px; margin: 0 6px 6px 0; }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <a href="{{ url('/') }}">
                <img src="{{ asset('assets/logos/light.png') }}" alt="{{ config('app.name') }}">
                {{ config('app.name') }}
            </a>
        </header>

        <main>
            @include($seo->bodyView, ['data' => $seo->data, 'seo' => $seo])
        </main>

        <footer>
            &copy; {{ now()->year }} {{ config('app.name') }}
            · <a href="{{ route('document.terms.index') }}">{{ __('links.terms_of_use') }}</a>
            · <a href="{{ route('document.privacy.index') }}">{{ __('links.privacy_policy') }}</a>
        </footer>
    </div>
</body>
</html>