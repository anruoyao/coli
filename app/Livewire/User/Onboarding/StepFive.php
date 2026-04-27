<?php

namespace App\Livewire\User\Onboarding;

use App\Rules\X\XRule;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;
use App\Enums\User\UserStatus;
use Illuminate\Validation\Rules\Password;

class StepFive extends Component
{
    public string $password;
    public string $password_confirmation;

    public function render()
    {
        return view('livewire.user.onboarding.step-five');
    }

    public function submitForm()
    {
        $rules = [
            'password' => ['required', 'string', XRule::join('min', config('user.validation.password.min')), XRule::join('max', config('user.validation.password.max')), 'confirmed']
        ];

        if(config('user.password_strength_control')) {
            array_push($rules['password'], Password::min(config('user.validation.password.min'))->letters()->mixedCase()->numbers()->symbols());
        }

        $this->validate(rules: $rules, attributes: [
            'password' => __('auth.password_label'),
            'password_confirmation' => __('auth.password_confirmation_label')
        ]);

        me()->update([
            'password' => Hash::make($this->password),
            'status' => UserStatus::ACTIVE
        ]);

        $this->redirect(route('user.desktop.index'));
    }
}
