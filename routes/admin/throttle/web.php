<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus 后台-API 限流监控路由
|--------------------------------------------------------------------------
*/

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Throttle\ThrottleMonitorController;

Route::get('/', [ThrottleMonitorController::class, 'index'])->name('admin.throttle.index');
