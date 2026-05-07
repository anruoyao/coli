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
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "JobPosting",
        "title": {{ json_encode($title) }},
        "description": {{ json_encode($description) }},
        "url": {{ json_encode($url) }},
        "hiringOrganization": {
            "@type": "Organization",
            "name": {{ json_encode(config('app.name')) }}
        }
    }
    </script>
</head>
<body>
    <article>
        <header>
            <h1>{{ $title }}</h1>
        </header>

        <p>{{ $description }}</p>

        <footer>
            <p>
                <a href="{{ $url }}">View job on {{ config('app.name') }}</a>
            </p>
        </footer>
    </article>
</body>
</html>
