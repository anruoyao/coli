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

Route::post('/create', [App\Http\Controllers\Api\User\Story\StoryController::class, 'create']);

Route::post('/media/upload', [App\Http\Controllers\Api\User\Story\StoryMediaController::class, 'uploadMedia']);

Route::delete('/media/delete', [App\Http\Controllers\Api\User\Story\StoryMediaController::class, 'deleteMedia']);