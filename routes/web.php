<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Ultimate Social Network Web Application.
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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cookie;

Route::name('user.')->group(function() {
    Route::get('/switch-language/{lang}', [App\Http\Controllers\User\Language\LanguageController::class, 'switchLanguage'])->name('language.switch');
    Route::get('/switch-theme/{theme}', [App\Http\Controllers\User\Theme\ThemeController::class, 'switchTheme'])->name('theme.switch');
});

Route::name('user.')->prefix('auth')->middleware(['guest'])->group(function() {
    Route::get('/login', [App\Http\Controllers\User\Auth\AuthController::class, 'index'])->name('auth.index');
    Route::get('/signup', [App\Http\Controllers\User\Auth\AuthController::class, 'signup'])->name('auth.signup');
    Route::get('/forgot-password', [App\Http\Controllers\User\Auth\AuthController::class, 'forgotPassword'])->name('auth.forgot');
    Route::get('/reset-password/{token}', [App\Http\Controllers\User\Auth\AuthController::class, 'resetPassword'])->name('auth.reset');
    Route::get('/confirm-signup/{token}', [App\Http\Controllers\User\Auth\AuthController::class, 'confirmSignup'])->name('auth.confirm-signup');
    Route::get('/forgot-success/{hashId}', [App\Http\Controllers\User\Auth\AuthController::class, 'forgotSuccess'])->name('auth.forgot-success');
    Route::get('/signup-success/{hashId}', [App\Http\Controllers\User\Auth\AuthController::class, 'signupSuccess'])->name('auth.signup-success');
});

Route::name('user.')->prefix('auth')->middleware(['auth'])->group(function() {
    Route::get('/link-account', [App\Http\Controllers\User\Auth\LinkerController::class, 'index'])->name('linker.index');
});

Route::name('user.')->prefix('onboarding')->middleware(['auth'])->group(function() {
    Route::get('/step-{step}', [App\Http\Controllers\User\Onboarding\OnboardingController::class, 'index'])->whereIn('step', ['one', 'two', 'three', 'four'])->name('onboarding.index');
});

Route::prefix('switcher')->get('/device/{type}', function ($type) {
    Cookie::queue('device_type', $type);

    return redirect()->back();
})->name('device.switch')->whereIn('type', ['desktop', 'mobile']);

Route::middleware(['user.status', 'auth:sanctum'])->group(function() {
    Route::get('/', function () {
        $deviceType = Cookie::get('device_type', 'desktop');
        
        if($deviceType == 'mobile') {
            return view('mobile::index');
        }

        else{
            return view('desktop::index');
        }
    })->name('user.desktop.index');

    Route::get('{any}', function (Request $request) {
        $deviceType = Cookie::get('device_type', 'desktop');
        
        if($deviceType == 'mobile') {
            return view('mobile::index');
        }

        else{
            return view('desktop::index');
        }
    })->where('any', '.*');
});