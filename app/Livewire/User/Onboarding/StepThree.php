<?php

namespace App\Livewire\User\Onboarding;

use Livewire\Component;

class StepThree extends Component
{
    public array $userData;

    public function mount()
    {
        $this->userData = [
            'city' => me()->city
        ];
    }

    public function render()
    {
        return view('livewire.user.onboarding.step-three');
    }

    public function submitForm()
    {
        $this->validate(rules: [
            'userData.city' => ['required', 'string', 'max:32']
        ], attributes: [
            'userData.city' => __('labels.city'),
        ]);

        me()->update([
            'city' => $this->userData['city']
        ]);

        $this->redirect(route('user.onboarding.index', 'four'));
    }

    public function skipStep()
    {
        if(empty(me()->city)) {
            me()->update(['city' => null]);
        }

        $this->redirect(route('user.onboarding.index', 'four'));
    }
}
