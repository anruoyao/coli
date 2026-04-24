<?php

namespace App\Livewire\User\Onboarding;

use Throwable;
use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Str;
use App\Enums\User\UserStatus;
use Illuminate\Validation\Rule;

class StepFour extends Component
{
    public string $username;
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
        $this->validate(rules: [
            'username' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z0-9._]+$/', Rule::unique('users', 'username')->ignore(me()->id)]
        ], attributes: [
            'username' => __('labels.username'),
        ], messages: [
            'username.regex' => 'The username can only contain letters, numbers, underscores, and dots.',
        ]);

        $user = me();

        $user->updateQuietly([
            'username' => $this->username,
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
