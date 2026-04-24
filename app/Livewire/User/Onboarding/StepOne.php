<?php

namespace App\Livewire\User\Onboarding;

use Livewire\Component;

class StepOne extends Component
{
    public array $userData;
    public array $microSteps;
    public array $birthDaysList;
    public array $birthMonthsList;
    public array $birthYearsList;

    public function mount()
    {
        $this->microSteps = [
            'first_name' => false,
            'last_name' => false,
            'birthday' => false,
            'gender' => false
        ];

        $this->birthMonthsList = year_months();

        $this->birthYearsList = birth_years();

        $this->assignMonthDays();

        $this->userData = [
            'first_name' => me()->first_name,
            'last_name' => me()->last_name,
            'birthday' => me()->birthday,
            'gender' => me()->gender
        ];
    }

    public function saveSelectOption(string $action, $value)
    {
        $this->$action($value);
    }

    public function render()
    {
        return view('livewire.user.onboarding.step-one');
    }

    public function submitForm()
    {
        if(empty($this->microSteps['first_name'])) {
            $this->saveFirstName();
        }

        else if(empty($this->microSteps['last_name'])) {
            $this->saveLastName();
        }

        else{
            if(empty(me()->birth_day) || empty(me()->birth_month) || empty(me()->birth_year)) {
                $this->addError('birthdate', __('auth.birthdate_required'));
            }
            else{
                $this->redirect(route('user.onboarding.index', 'two'));
            }
        }
    }

    private function saveFirstName()
    {
        $this->validate(rules: [
            'userData.first_name' => ['required', 'string', 'max:32']
        ], attributes: [
            'userData.first_name' => __('labels.name')
        ]);

        me()->update([
            'first_name' => $this->userData['first_name']
        ]);

        $this->microSteps['first_name'] = true;
    }

    private function saveLastName()
    {
        $rules = [
            'userData.last_name' => ['string', 'max:32']
        ];

        if((config('user.require.last_name') == true)) {
            array_push($rules['userData.last_name'], 'required');
        }

        $this->validate(rules: $rules, attributes: [
            'userData.last_name' => __('labels.last_name'),
        ]);

        me()->update([
            'last_name' => $this->userData['last_name']
        ]);

        $this->microSteps['last_name'] = true;
    }

    private function assignMonthDays()
    {
        if(me()->birth_month) {

            $birthYear = me()->birth_year ?? null;

            $this->birthDaysList = month_days(me()->birth_month, $birthYear);
        }
    }

    private function saveBirthDay($value) {
        if(is_numeric($value)) {
            me()->update(['birth_day' => $value]);
        }
    }

    private function saveBirthMonth($value) {
        if(is_numeric($value) && in_array($value, ['01','02','03','04','05','06','07','08','09','10','11', '12'])) {
            me()->update(['birth_month' => $value]);
            
            $this->assignMonthDays();
        }
    }

    private function saveBirthYear($value) {
        if(is_numeric($value)) {
            me()->update(['birth_year' => $value]);
        }
    }

    public function saveGender($value) {
        if(is_string($value) && in_array($value, ['male', 'female'])) {
            me()->update(['gender' => $value]);
        }
    }
}
