<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\User\Onboarding;

use App\Http\Controllers\Controller;

class OnboardingController extends Controller
{
    public function index($step)
    {
        $stepMap = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4];

        return view('onboarding::index', [
            'step' => $step,
            'stepNumber' => (isset($stepMap[$step]) ? $stepMap[$step] : 1),
            'totalSteps' => count($stepMap)
        ]);
    }
}
