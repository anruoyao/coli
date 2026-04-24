@extends('authLayout::index')

@section('pageContent')
    <div class="block">
        @livewire('user.auth.signup-success-form', ['confirmationData' => $confirmationData])
    </div>
@endsection