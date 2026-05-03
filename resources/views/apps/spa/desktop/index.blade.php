@extends('layouts.spa.apps.desktop.index')

@section('pageContent')
    <div id="colibriplus-desktop-app">
        @unless(config('app.hide_author_attribution'))
            @include('apps.spa.devnote')
        @endunless
    </div>
@endsection