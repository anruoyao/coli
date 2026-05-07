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

Route::get('/sitemap.xml', [App\Http\Controllers\Seo\SitemapController::class, 'index']);

Route::get('/sitemap-{type}-{page}.xml', [App\Http\Controllers\Seo\SitemapController::class, 'show'])
    ->where(['type' => '[a-z_]+', 'page' => '[0-9]+']);

Route::get('/publication/{hashId}', [App\Http\Controllers\Seo\ContentProxyController::class, 'post']);
Route::get('/@{username}', [App\Http\Controllers\Seo\ContentProxyController::class, 'profile'])->where('username', '[a-zA-Z0-9._]+');
Route::get('/marketplace/product/{hashId}', [App\Http\Controllers\Seo\ContentProxyController::class, 'product']);
Route::get('/jobs/{hashId}', [App\Http\Controllers\Seo\ContentProxyController::class, 'job']);

Route::prefix('switcher')->get('/device/{type}', function ($type) {
    Cookie::queue('device_type', $type);

    return redirect()->back();
})->name('device.switch')->whereIn('type', ['desktop', 'mobile']);

$spaEntry = function (Request $request) {
    $deviceType = Cookie::get('device_type', 'desktop');

    if ($deviceType == 'mobile') {
        return view('mobile::index');
    }

    return view('desktop::index');
};

Route::middleware(['guest_or_auth'])->group(function () use ($spaEntry) {
    Route::get('/', $spaEntry)->name('user.desktop.index');
    Route::get('/explore', $spaEntry);
    Route::get('/explore/{any}', $spaEntry)->where('any', '.*');
    Route::get('/marketplace', $spaEntry);
    Route::get('/jobs', $spaEntry);
});

Route::middleware(['user.status', 'auth:sanctum'])->group(function () use ($spaEntry) {
    Route::get('/bookmarks', $spaEntry);
    Route::get('/messenger', $spaEntry);
    Route::get('/messenger/{any}', $spaEntry)->where('any', '.*');
    Route::get('/settings', $spaEntry);
    Route::get('/settings/{any}', $spaEntry)->where('any', '.*');
    Route::get('/wallet', $spaEntry);
    Route::get('/live-stream', $spaEntry);
    Route::get('/stories/{story_uuid}', $spaEntry);
    Route::get('/new/post', $spaEntry);
    Route::get('/new/story', $spaEntry);

    Route::get('{any}', $spaEntry)->where('any', '.*');
});
