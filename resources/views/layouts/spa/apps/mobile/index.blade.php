<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

        <title>{{ config('app.name') }}</title>

        @include('layouts.parts.meta')
        @include('layouts.parts.favicons')

        @vite([
            'resources/js/spa/apps/mobile/bootstrap/application.js',
            config('assets.fonts.sans'),
            config('assets.fonts.mono')
        ])

        @if(theme_name() == 'dark')
            <link rel="stylesheet" href="{{ asset('build/assets/mobile-main-dark.css') }}?v={{ $buildNumber }}">
        @else
            @vite('resources/css/spa/apps/mobile/main.css')
        @endif

        @include('layouts.spa.apps.parts.pwa')
    </head>
    <body>
        <x-device-switcher.mobile></x-device-switcher.mobile>

        @yield('pageContent')

        @include('layouts.spa.apps.parts.embeds')
    </body>
</html>
