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
    // App 注册功能开关（客户端注册前检测是否需要邮箱验证码）
    Route::get('/config', [App\Http\Controllers\Api\User\Auth\EmailVerificationController::class, 'config']);

    Route::post('/register', [App\Http\Controllers\Api\User\Auth\AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/forgot-password', [App\Http\Controllers\Api\User\Auth\AuthController::class, 'forgotPassword'])->middleware('throttle:forgot');
    Route::post('/reset-password', [App\Http\Controllers\Api\User\Auth\AuthController::class, 'resetPassword'])->middleware('throttle:api.auth-password');

    // App 注册邮箱验证码（发送 / 重发）
    Route::post('/email-code/send', [App\Http\Controllers\Api\User\Auth\EmailVerificationController::class, 'sendCode'])->middleware('throttle:verification-code');
    Route::post('/email-code/resend', [App\Http\Controllers\Api\User\Auth\EmailVerificationController::class, 'resendCode'])->middleware('throttle:verification-code');

    // 访客面板内嵌登录：XHR 建立 web session，复用 login 限流器（成功后 SPA 无刷新切换）
    Route::post('/guest-login', [App\Http\Controllers\Api\Guest\Auth\GuestSessionAuthController::class, 'login'])->middleware('throttle:login');
});

// App 私有频道 socket 认证（替代网页的 session 版 /broadcasting/auth）
Route::prefix('broadcasting')->middleware(['auth:sanctum', 'throttle:api.broadcasting', 'abuse.guard'])->group(function () {
    Route::post('/auth', function (Illuminate\Http\Request $request) {
        return Illuminate\Support\Facades\Broadcast::auth($request);
    });
});

Route::prefix('translations')->middleware(['throttle:api.translations'])->group(base_path('routes/api/translations.php'));

// 访客公开只读 API（v1）：独立中间件链，明确不挂 auth:sanctum，
// 与现有登录用户 API 完全隔离。路由定义见 routes/api/guest.php。
Route::prefix('guest/v1')->middleware(['guest.enabled', 'guest.context', 'throttle:guest'])->group(base_path('routes/api/guest.php'));

// 分类限流 throttle:api.{category}：额度集中配置在 config/security.php 的 rate_limits，
// key 维度 = 登录用户(user_id) → device_id → IP（见 AppServiceProvider）。
// 认证组中间件顺序必须是 auth:sanctum → throttle → abuse.guard（throttle 需登录态取 user_id）。
Route::prefix('bootstrap')->middleware(['auth:sanctum', 'throttle:api.bootstrap', 'abuse.guard'])->group(base_path('routes/api/user/bootstrap.php'));

// 在线心跳（App 前后台切换，P0 在线用户数）
Route::prefix('presence')->middleware(['auth:sanctum', 'throttle:api.presence', 'abuse.guard'])->group(base_path('routes/api/user/presence.php'));

Route::prefix('settings')->middleware(['auth:sanctum', 'throttle:api.settings', 'abuse.guard'])->group(base_path('routes/api/user/account_settings.php'));

Route::prefix('auth')->middleware(['auth:sanctum', 'throttle:api.auth', 'abuse.guard'])->group(base_path('routes/api/user/auth.php'));

Route::prefix('post/editor')->middleware(['auth:sanctum', 'throttle:api.post-editor', 'abuse.guard'])->group(base_path('routes/api/user/post_editor.php'));

Route::prefix('story/editor')->middleware(['auth:sanctum', 'throttle:api.story-editor', 'abuse.guard'])->group(base_path('routes/api/user/story_editor.php'));

Route::prefix('timeline')->middleware(['auth:sanctum', 'throttle:api.timeline', 'abuse.guard'])->group(base_path('routes/api/user/timeline.php'));

Route::prefix('stories')->middleware(['auth:sanctum', 'throttle:api.stories', 'abuse.guard'])->group(base_path('routes/api/user/stories.php'));

Route::prefix('profile')->middleware(['auth:sanctum', 'throttle:api.profile', 'abuse.guard'])->group(base_path('routes/api/user/profile.php'));

Route::prefix('relations')->middleware(['auth:sanctum', 'throttle:api.relations', 'abuse.guard'])->group(base_path('routes/api/user/relations.php'));

Route::prefix('marketplace')->middleware(['auth:sanctum', 'throttle:api.marketplace', 'abuse.guard'])->group(base_path('routes/api/user/marketplace.php'));

Route::prefix('jobs')->middleware(['auth:sanctum', 'throttle:api.jobs', 'abuse.guard'])->group(base_path('routes/api/user/jobs.php'));

// messenger：聊天实时性高（进会话拉详情/消息 + 标记已读 + 未读对账），
// 突发请求密集，限流放宽（见 config rate_limits.messenger），避免正常操作互相挤爆触发 429。
Route::prefix('messenger')->middleware(['auth:sanctum', 'throttle:api.messenger', 'abuse.guard'])->group(base_path('routes/api/user/messenger.php'));

Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:api.admin', 'abuse.guard'])->group(base_path('routes/api/user/admin.php'));

Route::prefix('recommendations')->middleware(['auth:sanctum', 'throttle:api.recommendations', 'abuse.guard'])->group(base_path('routes/api/user/recommend.php'));

Route::prefix('explore')->middleware(['auth:sanctum', 'throttle:api.explore', 'abuse.guard'])->group(base_path('routes/api/user/explore.php'));

Route::prefix('notifications')->middleware(['auth:sanctum', 'throttle:api.notifications', 'abuse.guard'])->group(base_path('routes/api/user/notifications.php'));

Route::prefix('autocompletes')->middleware(['auth:sanctum', 'throttle:api.autocompletes', 'abuse.guard'])->group(base_path('routes/api/user/autocompletes.php'));

Route::prefix('translator')->middleware(['auth:sanctum', 'throttle:api.translator', 'abuse.guard'])->group(base_path('routes/api/user/translator.php'));

Route::prefix('feedback')->middleware(['auth:sanctum', 'throttle:api.feedback', 'abuse.guard'])->group(base_path('routes/api/user/feedback.php'));

Route::prefix('bookmarks')->middleware(['auth:sanctum', 'throttle:api.bookmarks', 'abuse.guard'])->group(base_path('routes/api/user/bookmarks.php'));

Route::prefix('wallet')->middleware(['auth:sanctum', 'throttle:api.wallet', 'abuse.guard'])->group(base_path('routes/api/user/wallet.php'));

Route::prefix('system')->middleware(['throttle:api.system'])->group(base_path('routes/api/system/master.php'));

Route::prefix('ads')->middleware(['throttle:api.ads'])->group(base_path('routes/api/ads/ad.php'));

Route::prefix('tips')->middleware(['auth:sanctum', 'throttle:api.tips', 'abuse.guard'])->group(base_path('routes/api/user/tips.php'));

Route::prefix('pins')->middleware(['auth:sanctum', 'throttle:api.pins', 'abuse.guard'])->group(base_path('routes/api/user/pins.php'));

Route::prefix('ai')->middleware(['auth:sanctum', 'throttle:api.ai', 'abuse.guard'])->group(base_path('routes/api/ai/user.php'));
