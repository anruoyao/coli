<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>
        
        @include('layouts.parts.meta')
        @include('layouts.parts.favicons')

        @vite([
            'resources/js/business/main.js',
            config('assets.fonts.sans'),
            config('assets.fonts.mono')
        ])

        @if(theme_name() == 'dark')
            <link rel="stylesheet" href="{{ asset('build/assets/business-main-dark.css') }}?v={{ $buildNumber }}">
        @else
            @vite('resources/css/business/main.css')
        @endif

        @stack('styles')
        
        @stack('scripts')

        @livewireStyles
    </head>

    <body @class(['pt-24', (theme_name() == 'dark' ? 'bg-black' : 'bg-fill-fv')])>
        @if(theme_name() == 'light')
            <img src="{{ asset('assets/backgrounds/modal-bg.png') }}" alt="Background" class="fixed opacity-50 w-full inset-0 -z-10">
        @endif
        <x-main>
            @include('businessLayout::parts.sidebar')
    
            @include('businessLayout::parts.header')
            
            <x-container>
                <div class="app-min-vh px-8">
                    @yield('pageContent')
                </div>
            </x-container>
    
            <x-modals.confirm.confirm></x-modals.confirm.confirm>
    
            @livewireScripts
        </x-main>
    </body>
</html>