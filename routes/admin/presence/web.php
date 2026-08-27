<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus 后台-在线用户路由
|--------------------------------------------------------------------------
*/

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Presence\PresenceController;

Route::get('/', [PresenceController::class, 'index'])->name('admin.presence.index');