<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\Admin\Marketing\CampaignController::class, 'index'])->name('admin.marketing.index');

Route::get('/create', [App\Http\Controllers\Admin\Marketing\CampaignController::class, 'create'])->name('admin.marketing.create');

Route::post('/store', [App\Http\Controllers\Admin\Marketing\CampaignController::class, 'store'])->name('admin.marketing.store');

Route::get('/{id}', [App\Http\Controllers\Admin\Marketing\CampaignController::class, 'show'])->name('admin.marketing.show')
    ->whereNumber('id');

Route::post('/{id}/send', [App\Http\Controllers\Admin\Marketing\CampaignController::class, 'send'])->name('admin.marketing.send')
    ->whereNumber('id');

Route::post('/{id}/cancel', [App\Http\Controllers\Admin\Marketing\CampaignController::class, 'cancel'])->name('admin.marketing.cancel')
    ->whereNumber('id');