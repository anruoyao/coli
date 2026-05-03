@extends('authLayout::index')

@section('pageContent')
    <div class="block">
        @livewire('user.auth.login-form')
    </div>
@endsection