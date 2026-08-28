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

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::post('/sanctum/token', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'device_name' => 'required',
    ]);

    // 账号维度限流：同一邮箱连续失败锁定（15 分钟窗口，IP 维度见 throttle:login）
    $accountKey = 'login:account:' . strtolower((string) $request->input('email'));
    if (RateLimiter::tooManyAttempts($accountKey, (int) config('security.auth.login_max_failures_per_account', 5))) {
        return response()->json([
            'status'  => 'error',
            'code'    => 429,
            'message' => __('auth.throttle', ['seconds' => 15 * 60]),
        ], 429);
    }

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        RateLimiter::hit($accountKey, 15 * 60);
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    RateLimiter::clear($accountKey);

    // 封禁/停用账号禁止登录
    if (in_array($user->status, [\App\Enums\User\UserStatus::BLOCKED, \App\Enums\User\UserStatus::SUSPENDED])) {
        return response()->json([
            'status' => 'error',
            'code' => 403,
            'message' => __('api/auth/user_status_' . $user->status->value),
            'data' => [
                'user_status' => $user->status->value,
                'reason' => $user->status_reason,
            ],
        ], 403);
    }

    prune_user_tokens($user, (int) config('security.auth.max_tokens_per_account', 10));

    return $user->createToken($request->device_name)->plainTextToken;
})->middleware('throttle:login');

Route::prefix('auth')->middleware(['throttle:60,60'])->group(function () {
    Route::post('/register', [App\Http\Controllers\Api\User\Auth\AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/forgot-password', [App\Http\Controllers\Api\User\Auth\AuthController::class, 'forgotPassword'])->middleware('throttle:forgot');
    Route::post('/reset-password', [App\Http\Controllers\Api\User\Auth\AuthController::class, 'resetPassword'])->middleware('throttle:10,60');
});

// App 私有频道 socket 认证（替代网页的 session 版 /broadcasting/auth）
Route::prefix('broadcasting')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(function () {
    Route::post('/auth', function (Illuminate\Http\Request $request) {
        return Illuminate\Support\Facades\Broadcast::auth($request);
    });
});

Route::prefix('translations')->middleware(['throttle:60,1'])->group(base_path('routes/api/translations.php'));

Route::prefix('bootstrap')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/bootstrap.php'));

// 在线心跳（App 前后台切换，P0 在线用户数）
Route::prefix('presence')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/presence.php'));

Route::prefix('settings')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/account_settings.php'));

Route::prefix('auth')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/auth.php'));

Route::prefix('post/editor')->middleware(['auth:sanctum', 'throttle:240,1', 'abuse.guard'])->group(base_path('routes/api/user/post_editor.php'));

Route::prefix('story/editor')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/story_editor.php'));

Route::prefix('timeline')->middleware(['auth:sanctum', 'throttle:240,1', 'abuse.guard'])->group(base_path('routes/api/user/timeline.php'));

Route::prefix('stories')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/stories.php'));

Route::prefix('profile')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/profile.php'));

Route::prefix('relations')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/relations.php'));

Route::prefix('marketplace')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/marketplace.php'));

Route::prefix('jobs')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/jobs.php'));

Route::prefix('messenger')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/messenger.php'));

Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/admin.php'));

Route::prefix('recommendations')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/recommend.php'));

Route::prefix('explore')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/explore.php'));

Route::prefix('notifications')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/notifications.php'));

Route::prefix('autocompletes')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/autocompletes.php'));

Route::prefix('translator')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/translator.php'));

Route::prefix('feedback')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/feedback.php'));

Route::prefix('bookmarks')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/bookmarks.php'));

Route::prefix('wallet')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/wallet.php'));

Route::prefix('system')->middleware(['throttle:60,1'])->group(base_path('routes/api/system/master.php'));

Route::prefix('ads')->middleware(['throttle:60,1'])->group(base_path('routes/api/ads/ad.php'));

Route::prefix('tips')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/tips.php'));

Route::prefix('pins')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/user/pins.php'));

Route::prefix('ai')->middleware(['auth:sanctum', 'throttle:60,1', 'abuse.guard'])->group(base_path('routes/api/ai/user.php'));
