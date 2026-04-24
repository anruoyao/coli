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

Route::name('downloads.')->prefix('file-downloads')->group(function() {
    Route::get('/post/download-document/{mediaId}/file', [App\Http\Controllers\Downloads\Documents\DocumentDownloadController::class, 'downloadDocument'])->name('document.index');                                                                           
});
