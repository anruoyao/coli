@extends('authLayout::index')

@section('pageContent')
    <div class="block">
        @livewire('user.auth.signup-form')
    </div>
@endsection