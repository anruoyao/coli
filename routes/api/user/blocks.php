<?php

use Illuminate\Support\Facades\Route;

Route::post('/block/user', [App\Http\Controllers\Api\User\Block\BlockController::class, 'blockUser']);
Route::post('/unblock/user', [App\Http\Controllers\Api\User\Block\BlockController::class, 'unblockUser']);
Route::get('/blocked/users', [App\Http\Controllers\Api\User\Block\BlockController::class, 'getBlockedUsers']);
