<?php

namespace App\Livewire\User\Auth;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Facades\Mail;
use App\Mail\User\Auth\ResetPasswordMail;
use App\Services\Auth\PasswordResetService;

class Forgot extends Component
{
    public string $emailAddress;

    public function render()
    {
        return view('livewire.user.auth.forgot');
    }

    public function submitForm()
    {
        $this->validate(rules: [
            'emailAddress' => ['required', 'string', 'email', 'max:62', 'exists:users,email']
        ], messages: [
            'emailAddress.exists' => __('auth.email_not_found')
        ], attributes: [
            'emailAddress' => __('auth.email')
        ]);

        $userData = User::where('email', $this->emailAddress)->first();
        $emailConfirmation = app(PasswordResetService::class)->issue($this->emailAddress);

        Mail::to($this->emailAddress)->locale(app()->getLocale())->queue(new ResetPasswordMail([
            'name' => $userData->name,
            'link' => route('user.auth.reset', ['token' => $emailConfirmation->token])
        ]));

        $this->redirect(route('user.auth.forgot-success', ['hashId' => $emailConfirmation->hash_id]));
    }
}
