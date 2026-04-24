<?php

namespace App\Livewire\User\Auth;

use Livewire\Component;
use Illuminate\Support\Str;
use App\Models\EmailConfirmation;
use App\Support\SocialLoginDrivers;
use App\Services\Blacklist\BlacklistService;
use App\Events\User\Auth\UserRegisteredEvent;

class SignupForm extends Component
{
    public string $emailAddress;
    public $activeSocialDrivers;
    public $showAllSocialOptions = false;

    public function mount(SocialLoginDrivers $socialLoginDrivers)
    {
        $this->activeSocialDrivers = $socialLoginDrivers->getActiveDrivers();
    }

    public function showAllSocialLoginOptions()
    {
        $this->showAllSocialOptions = true;
    }

    public function render()
    {
        return view('livewire.user.auth.signup-form');
    }

    public function submitForm()
    {
        $this->validate(rules: [
            'emailAddress' => ['required', 'string', 'email', 'max:62', 'unique:users,email']
        ], attributes: [
            'emailAddress' => __('auth.email')
        ]);

        if($this->checkIfEmailBlacklisted()) {
            $this->addError('emailAddress', __('auth.email_blocked'));

            return false;
        }
        
        $emailToken = Str::uuid();

        $emailConfirmation = EmailConfirmation::create([
            'email' => $this->emailAddress,
            'token' => $emailToken
        ]);

        event(new UserRegisteredEvent([
            'email' => $this->emailAddress,
            'link' => route('user.auth.confirm-signup', ['token' => $emailToken])
        ]));

        $this->redirect(route('user.auth.signup-success', ['hashId' => $emailConfirmation->hash_id]));
    }

    private function checkIfEmailBlacklisted()
    {
        $blacklistService = app(BlacklistService::class);
        
        return $blacklistService->isEmailBlacklisted($this->emailAddress);
    }
}
