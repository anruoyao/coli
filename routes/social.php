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

use Illuminate\Support\Facades\Route;

Route::name('social-login.')->prefix('social-login')->group(function() {
    // Google login routes
    Route::get('/auth/google', [App\Http\Controllers\User\Auth\Social\GoogleAuthController::class, 'index'])->name('google.redirect');

    Route::get('/callback/google',[App\Http\Controllers\User\Auth\Social\GoogleAuthController::class, 'callbackHandler'])->name('google.callback');
    
    // Apple login routes
    Route::get('/auth/apple', [App\Http\Controllers\User\Auth\Social\AppleAuthController::class, 'index'])->name('apple.redirect');

    Route::get('/callback/apple',[App\Http\Controllers\User\Auth\Social\AppleAuthController::class, 'callbackHandler'])->name('apple.callback');

    // Telegram login routes
    Route::get('/auth/telegram', [App\Http\Controllers\User\Auth\Social\TelegramAuthController::class, 'index'])->name('telegram.redirect');

    Route::get('/callback/telegram',[App\Http\Controllers\User\Auth\Social\TelegramAuthController::class, 'callbackHandler'])->name('telegram.callback');

    // Twitter login routes
    Route::get('/auth/twitter', [App\Http\Controllers\User\Auth\Social\TwitterAuthController::class, 'index'])->name('twitter.redirect');

    Route::get('/callback/twitter',[App\Http\Controllers\User\Auth\Social\TwitterAuthController::class, 'callbackHandler'])->name('twitter.callback');

    // Facebook login routes
    Route::get('/auth/facebook', [App\Http\Controllers\User\Auth\Social\FacebookAuthController::class, 'index'])->name('facebook.redirect');

    Route::get('/callback/facebook',[App\Http\Controllers\User\Auth\Social\FacebookAuthController::class, 'callbackHandler'])->name('facebook.callback');

    // Discord login routes
    Route::get('/auth/discord', [App\Http\Controllers\User\Auth\Social\DiscordAuthController::class, 'index'])->name('discord.redirect');

    Route::get('/callback/discord',[App\Http\Controllers\User\Auth\Social\DiscordAuthController::class, 'callbackHandler'])->name('discord.callback');

    // VK login routes
    Route::get('/auth/vk', [App\Http\Controllers\User\Auth\Social\VkAuthController::class, 'index'])->name('vk.redirect');

    Route::get('/callback/vk',[App\Http\Controllers\User\Auth\Social\VkAuthController::class, 'callbackHandler'])->name('vk.callback');

    // LinkedIn login routes
    Route::get('/auth/linkedin', [App\Http\Controllers\User\Auth\Social\LinkedinAuthController::class, 'index'])->name('linkedin.redirect');

    Route::get('/callback/linkedin',[App\Http\Controllers\User\Auth\Social\LinkedinAuthController::class, 'callbackHandler'])->name('linkedin.callback');

    // TikTok login routes
    Route::get('/auth/tiktok', [App\Http\Controllers\User\Auth\Social\TikTokAuthController::class, 'index'])->name('tiktok.redirect');

    Route::get('/callback/tiktok',[App\Http\Controllers\User\Auth\Social\TikTokAuthController::class, 'callbackHandler'])->name('tiktok.callback');

    // Microsoft login routes
    Route::get('/auth/microsoft', [App\Http\Controllers\User\Auth\Social\MicrosoftAuthController::class, 'index'])->name('microsoft.redirect');

    Route::get('/callback/microsoft',[App\Http\Controllers\User\Auth\Social\MicrosoftAuthController::class, 'callbackHandler'])->name('microsoft.callback');
});