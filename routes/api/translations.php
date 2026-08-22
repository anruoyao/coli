<?php

use Illuminate\Support\Facades\Route;

Route::get('/app', function () {
	$locale = request()->get('locale', 'en');
	$localePath = base_path("lang/{$locale}/api/index.php");

	if(! file_exists($localePath)) {
		return response()->json([
			'data' => []
		]);
	}

    return response()->json([
		'data' => require_once $localePath,
	]);
});