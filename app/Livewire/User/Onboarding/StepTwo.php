<?php

namespace App\Livewire\User\Onboarding;

use App\Services\World\WorldService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Component;

class StepTwo extends Component
{
    public array $worldCountries;

    public function mount(WorldService $world)
    {
        $this->worldCountries = collect($world->countries())->map(function($value, $key) {
            return [
                'key' => $key,
                'value' => $value,
            ];
        })->values()->toArray();
    }

    public function render()
    {
        return view('livewire.user.onboarding.step-two');
    }

    public function saveSelectOption(string $action, $value)
    {
        $this->$action($value);
    }

    private function saveCountry(string $countryCode)
    {
        $countryKeys = collect( $this->worldCountries)->map(function($item) {
            return $item['key'];
        })->toArray();

        $validator = Validator::make(['country' => $countryCode], [
            'country' => ['required', 'string', 'size:2', 'uppercase', Rule::in($countryKeys)]
        ]);

        if ($validator->fails()) {
            $this->addError('country', __('validation.enum', ['attribute' => __('labels.country')]));
        }

        else{
            me()->update(['country' => $countryCode]);
        }
    }

    public function submitForm()
    {
        if(empty(me()->country)) {
            $this->addError('country', __('validation.select_or_skip', ['attribute' => __('labels.country')]));
        }

        else{
            $this->redirect(route('user.onboarding.index', 'three'));
        }
    }

    public function skipStep()
    {
        if(empty(me()->country)) {
            me()->update(['country' => config('user.country')]);
        }

        $this->redirect(route('user.onboarding.index', 'three'));
    }
}
