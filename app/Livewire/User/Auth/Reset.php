<?php

namespace App\Livewire\User\Auth;

use App\Models\User;
use App\Rules\X\XRule;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Services\Auth\PasswordResetService;
use Illuminate\Validation\Rules\Password;

class Reset extends Component
{
    public $confirmationData;
    public string $resetToken;
    public string $newPassword;
    public $userData;

    public function mount()
    {
        $this->resetToken = (string) $this->confirmationData->token;
        $this->confirmationData = app(PasswordResetService::class)->findValidToken($this->resetToken);

        abort_unless($this->confirmationData, 404);

        $this->userData = User::where('email', $this->confirmationData->email)->first();
    }

    public function render()
    {
        return view('livewire.user.auth.reset');
    }

    public function submitForm()
    {
        $rules = ['required', 'string', XRule::join('min', config('user.validation.password.min')), XRule::join('max', config('user.validation.password.max'))];

        if(config('user.password_strength_control')) {
            array_push($rules, Password::min(config('user.validation.password.min'))->letters()->mixedCase()->numbers()->symbols());
        }

        $this->validate(rules: [
            'newPassword' => $rules
        ], attributes: [
            'newPassword' => __('auth.password_label')
        ]);

        $user = app(PasswordResetService::class)->resetPassword($this->resetToken, $this->newPassword);

        abort_unless($user, 404);

        Auth::login($user);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $this->redirect(route('user.desktop.index'));
    }
}
