<?php

namespace App\Livewire\User\Onboarding;

use Throwable;
use App\Models\User;
use App\Rules\X\XRule;
use Livewire\Component;
use Illuminate\Support\Str;
use App\Enums\User\UserStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StepFour extends Component
{
    public string $username;
    public string $password = '';
    public string $passwordConfirmation = '';
    public $isAvailable = null;

    public function mount()
    {
        $this->username = me()->username;
    }

    public function render()
    {
        return view('livewire.user.onboarding.step-four');
    }

    public function checkAvailability()
    {
        if(Str::length($this->username) >= 1) {
            $this->isAvailable = (User::where('username', $this->username)->exists() != true);
        }
    }

    public function updatedUsername()
    {
        $this->reset('isAvailable');

        $this->checkAvailability();
    }

    public function submitForm()
    {
        $passwordRules = ['required', 'string', XRule::join('min', config('user.validation.password.min')), XRule::join('max', config('user.validation.password.max'))];

        if (config('user.password_strength_control')) {
            $passwordRules[] = Password::min(config('user.validation.password.min'))
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        }

        $this->validate(rules: [
            'username' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z0-9._]+$/', Rule::unique('users', 'username')->ignore(me()->id)],
            'password' => $passwordRules,
            'passwordConfirmation' => ['required', 'string', 'same:password']
        ], attributes: [
            'username' => __('labels.username'),
            'password' => __('auth.password_label'),
            'passwordConfirmation' => __('auth.password_confirmation_label'),
        ], messages: [
            'username.regex' => 'The username can only contain letters, numbers, underscores, and dots.',
        ]);

        $user = me();

        $user->updateQuietly([
            'username' => $this->username,
            'password' => $this->password,
            'status' => UserStatus::ACTIVE
        ]);

        $this->makeFollowList();

        $this->redirect(route('user.desktop.index'));
    }

    private function makeFollowList()
    {
        try {
            $followList = config('user.auto_follow_list');

            if(empty($followList)) {
                return false;
            }

            $followList = explode(',', $followList);

            $followList = User::active()->whereIn('username', $followList)->get();

            if($followList->isNotEmpty()) {
                foreach($followList as $userData) {
                    me()->follow($userData);
                }
            }

            return true;
        } catch (Throwable $th) {
            logger()->error('Error making follow list on onboard.', [
                'error' => $th->getMessage(),
                'followList' => $followList
            ]);

            return false;
        }
    }
}
