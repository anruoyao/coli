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

Route::get('/draft', [App\Http\Controllers\Api\User\Timeline\PostController::class, 'getDraftPost']);

Route::post('/create', [App\Http\Controllers\Api\User\Timeline\PostController::class, 'createPost']);

Route::post('/media/image/upload', [App\Http\Controllers\Api\User\Timeline\PostImageController::class, 'uploadImage']);

Route::post('/media/video/upload', [App\Http\Controllers\Api\User\Timeline\PostVideoController::class, 'uploadVideo']);

Route::post('/media/audio/upload', [App\Http\Controllers\Api\User\Timeline\PostAudioController::class, 'uploadAudio']);

Route::post('/media/document/upload', [App\Http\Controllers\Api\User\Timeline\PostDocumentController::class, 'uploadDocument']);

Route::delete('/media/delete', [App\Http\Controllers\Api\User\Timeline\PostMediaController::class, 'deleteMedia']);

Route::post('/poll/create', [App\Http\Controllers\Api\User\Timeline\PostPollController::class, 'createPoll']);

Route::delete('/poll/delete', [App\Http\Controllers\Api\User\Timeline\PostPollController::class, 'deletePoll']);

Route::post('/gif/create', [App\Http\Controllers\Api\User\Timeline\PostGifController::class, 'createGif']);

Route::post('/link/preview', [App\Http\Controllers\Api\User\Timeline\PostController::class, 'previewLink']);

Route::delete('/link/delete', [App\Http\Controllers\Api\User\Timeline\PostController::class, 'deleteLinkSnapshot']);
