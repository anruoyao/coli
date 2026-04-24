<?php

use Illuminate\Support\Facades\Route;

Route::get('/general', [App\Http\Controllers\Admin\Config\ConfigController::class, 'general'])->name('admin.config.general');

Route::get('/email', [App\Http\Controllers\Admin\Config\ConfigController::class, 'email'])->name('admin.config.email');

Route::post('/email/testing', [App\Http\Controllers\Admin\Config\ConfigController::class, 'emailTesting'])->name('admin.config.email-testing');

Route::get('/notifications', [App\Http\Controllers\Admin\Config\ConfigController::class, 'notifications'])->name('admin.config.notifications');

Route::get('/api', [App\Http\Controllers\Admin\Config\ConfigController::class, 'api'])->name('admin.config.api');

Route::get('/verification', [App\Http\Controllers\Admin\Config\ConfigController::class, 'verification'])->name('admin.config.verification');