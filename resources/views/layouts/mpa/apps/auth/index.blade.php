<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

        <title>{{ config('app.name') }}</title>

        @include('layouts.parts.meta')
        @include('layouts.parts.favicons')

        @vite([
            config('assets.fonts.sans'),
            config('assets.fonts.mono')
        ])

        @if(theme_name() == 'dark')
            <link rel="stylesheet" href="{{ asset('build/assets/desktop-auth-dark.css') }}?v={{ $buildNumber }}">
        @else
            @vite('resources/css/spa/apps/desktop/auth.css')
        @endif
        
        @livewireStyles
        @stack('styles')
    </head>
    <body class="bg-bg-pr pt-14" style="min-width: 320px;">
        @include('layouts.mpa.parts.header')

        <div class="flex-col flex min-h-screen">
            <div class="flex justify-center py-12 md:py-24 px-4 flex-1">
                <div class="auth-content">
                    @yield('pageContent')
                </div>
            </div>
            @include('layouts.mpa.parts.footer')
        </div>

        @stack('scripts')
        @livewireScripts
    </body>
</html>