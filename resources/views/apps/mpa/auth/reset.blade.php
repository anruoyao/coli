@extends('authLayout::index')

@section('pageContent')
    <div class="block">
        @livewire('user.auth.reset-form', ['confirmationData' => $confirmationData])
    </div>
@endsection