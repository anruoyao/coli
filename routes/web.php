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
    Route::middleware(['features.status:dark_theme'])->get('/switch-theme/{theme}', [App\Http\Controllers\User\Theme\ThemeController::class, 'switchTheme'])->name('theme.switch');
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

Route::name('admin.')->prefix(config('app.admin_prefix'))->middleware(['guest'])->group(function() {
    Route::get('/login', [App\Http\Controllers\Admin\Auth\AuthController::class, 'login'])->name('auth.login');
});

Route::name('user.')->prefix('auth')->middleware(['auth'])->group(function() {
    Route::middleware(['features.status:link_accounts'])
        ->get('/link-account', [App\Http\Controllers\User\Auth\LinkerController::class, 'index'])
        ->name('linker.index');

    Route::get('/logout', [App\Http\Controllers\User\Auth\AuthController::class, 'logout'])->name('auth.logout');
});

Route::name('user.')->prefix('onboarding')->middleware(['auth'])->group(function() {
    Route::get('/step/{step}', [App\Http\Controllers\User\Onboarding\OnboardingController::class, 'index'])->name('onboarding.index');
});

Route::prefix('switcher')->get('/device/{type}', function ($type) {

    // 1 year
    Cookie::queue('device_type', $type, (60 * 60 * 24 * 365));

    return redirect()->back();
})->name('device.switch')->whereIn('type', ['desktop', 'mobile']);

// 前端 SPA shell / 公开 SEO 页统一处理：
// - 已登录用户渲染桌面/mobile SPA shell（保持原行为，user.status 处理封禁/引导）；
// - 未登录访客命中可公开收录路径时，由 SeoController 服务端输出 SEO HTML（meta + JSON-LD + 正文快照）；
// - 未登录访客命中其余路径：重定向登录页（保持原行为）。
Route::middleware(['user.status'])->group(function() {
    Route::get('/', [App\Http\Controllers\SeoController::class, '__invoke'])->name('user.desktop.index');

    // Sitemap：/sitemap.xml（索引）+ /sitemap-{type}-{page}.xml（分片）
    Route::get('sitemap.xml', [App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap.index');
    Route::get('sitemap-{file}', [App\Http\Controllers\SitemapController::class, 'chunk'])->name('sitemap.chunk')
        ->where('file', '^[a-z]+-[0-9]+\.xml$');

    Route::get('{any}', [App\Http\Controllers\SeoController::class, '__invoke'])
        ->where('any', '^(?!.*\.(?:js|css|ts|map|png|jpe?g|gif|svg|webp|ico|woff2?|eot|ttf|otf|mp4|webm|txt|json|xml|wasm|ht?ml?|pdf)$).+$');
});
