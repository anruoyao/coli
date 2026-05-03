@extends('authLayout::index')

@section('pageContent')
    <div class="block">
        <div>
            <div class="mb-8">
                <x-auth.parts.form-header title="{{ __('labels.signup_almost_done.title') }}">
                    <x-slot:icon>
                        <x-ui-icon name="user-02" type="line"></x-ui-icon>
                    </x-slot:icon>
                    <x-slot:caption>
                        {{ __('labels.signup_almost_done.caption') }}
                    </x-slot:caption>
                </x-auth.parts.form-header>
            </div>
            <div class="mb-6">
                <span class="text-lab-sc text-par-m mb-2 inline-block">
                    {{ __('labels.signup_steps', ['current' => $stepNumber, 'total' => $totalSteps]) }}
                </span>
                <div class="h-0.5 bg-fill-tr leading-none">
                    <div class="h-0.5 bg-brand-900 min-w-4" style="width: {{ round(($stepNumber / $totalSteps) * 100)}}%;"></div>
                </div>
            </div>
            
            @livewire("user.onboarding.step-{$step}")

            <div class="block">
                <p class="text-par-s text-lab-sc text-center">
                    {!! __('auth.auth_agreement', ['app_name' => config('app.name')]) !!}
                </p>
            </div>
        </div>
    </div>
@endsection