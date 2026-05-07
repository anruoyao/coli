<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $url }}">
    <meta name="robots" content="index, follow">

    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:type" content="profile">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="profile:username" content="{{ $username ?? '' }}">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ProfilePage",
        "name": {{ json_encode($title) }},
        "description": {{ json_encode($description) }},
        "image": {{ json_encode($image) }},
        "mainEntity": {
            "@type": "Person",
            "name": {{ json_encode($title) }},
            "image": {{ json_encode($image) }},
            "url": {{ json_encode($url) }}
        }
    }
    </script>
</head>
<body>
    <article>
        <header>
            <h1>{{ $title }}</h1>
        </header>

        <img src="{{ $image }}" alt="{{ $title }}" loading="lazy">

        <p>{{ $description }}</p>

        <footer>
            <p>
                <a href="{{ $url }}">View profile on {{ config('app.name') }}</a>
            </p>
        </footer>
    </article>
</body>
</html>
