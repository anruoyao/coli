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
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="{{ $site_name }}">
    <meta property="article:published_time" content="{{ $published_at }}">
    <meta property="article:author" content="{{ $author }}">

    <meta name="twitter:card" content="{{ $post_type === 'text' ? 'summary' : 'summary_large_image' }}">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": {{ json_encode($title) }},
        "description": {{ json_encode($description) }},
        "image": {{ json_encode($image) }},
        "author": {
            "@type": "Person",
            "name": {{ json_encode($author) }}
        },
        "datePublished": {{ json_encode($published_at) }},
        "publisher": {
            "@type": "Organization",
            "name": {{ json_encode($site_name) }},
            "url": {{ json_encode(config('app.url')) }}
        },
        "mainEntityOfPage": {
            "@type": "WebPage",
            "@id": {{ json_encode($url) }}
        }
    }
    </script>
</head>
<body>
    <article>
        <header>
            <h1>{{ $title }}</h1>
            <p>
                <span>{{ $author }}</span>
                <time datetime="{{ $published_at }}">{{ $published_at }}</time>
            </p>
        </header>

        @if (! empty($content))
            <p>{{ $content }}</p>
        @endif

        @if ($post_type === 'poll')
            <section>
                <h2>Poll</h2>
                <p>{{ $author }} created a poll on {{ $site_name }}</p>
            </section>
        @endif

        @if (! empty($media_items))
            <section>
                @foreach ($media_items as $index => $media)
                    @if (in_array($media['type'], ['image', 'gif']))
                        <figure>
                            <img
                                src="{{ $media['url'] ?? $media['thumbnail'] }}"
                                alt="{{ $content ?: $author . ' shared an image on ' . $site_name }}"
                                loading="lazy"
                            >
                            @if ($content && $index === 0)
                                <figcaption>{{ $content }}</figcaption>
                            @endif
                        </figure>
                    @elseif ($media['type'] === 'video')
                        <figure>
                            <video
                                controls
                                preload="metadata"
                                poster="{{ $media['thumbnail'] ?? '' }}"
                            >
                                <source src="{{ $media['url'] }}" type="video/mp4">
                            </video>
                            @if ($content && $index === 0)
                                <figcaption>{{ $content }}</figcaption>
                            @endif
                        </figure>
                    @elseif ($media['type'] === 'audio')
                        <figure>
                            <audio controls preload="metadata">
                                <source src="{{ $media['url'] }}">
                            </audio>
                            <figcaption>{{ $author }} shared audio on {{ $site_name }}</figcaption>
                        </figure>
                    @elseif ($media['type'] === 'document')
                        <p>
                            <a href="{{ $media['url'] }}">{{ $author }} shared a document on {{ $site_name }}</a>
                        </p>
                    @endif
                @endforeach
            </section>
        @endif

        <footer>
            <p>
                <a href="{{ $url }}">View on {{ $site_name }}</a>
            </p>
        </footer>
    </article>
</body>
</html>
