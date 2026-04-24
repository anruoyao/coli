@extends('authLayout::index')

@section('pageContent')
    <div class="block">
        @livewire('user.auth.forgot-form')
    </div>
@endsection