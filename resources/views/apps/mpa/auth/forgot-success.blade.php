@extends('authLayout::index')

@section('pageContent')
    <div class="block">
        @livewire('user.auth.forgot-success-form', ['confirmationData' => $confirmationData])
    </div>
@endsection