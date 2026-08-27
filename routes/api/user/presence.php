<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus App 在线心跳路由（auth:sanctum）
|--------------------------------------------------------------------------
*/

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\Presence\PresenceController;

Route::post('/heartbeat', [PresenceController::class, 'heartbeat'])->name('presence.heartbeat');