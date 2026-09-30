<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - Guest（访客）公开只读 API，v1
|--------------------------------------------------------------------------
| 全部端点仅 GET；访客身份与限流由路由组中间件保证
| （guest.enabled / guest.context / throttle:guest，挂载见 routes/api.php）。
*/

use App\Http\Controllers\Api\Guest;
use Illuminate\Support\Facades\Route;

// 访客初始化（品牌 / 认证状态 / 能力矩阵 / 可见导航）
Route::get('/bootstrap', [Guest\GuestBootstrapController::class, 'bootstrap']);

// 精选公开帖子流
Route::get('/feed', [Guest\GuestFeedController::class, 'feed']);

// 公开帖子详情 + 评论分页
Route::get('/post/{hashId}', [Guest\GuestPostController::class, 'show']);
Route::get('/post/{hashId}/comments', [Guest\GuestPostController::class, 'comments']);

// 脱敏公开用户主页 + 其公开帖子（不开放粉丝/关注列表）
Route::get('/profile/{username}', [Guest\GuestProfileController::class, 'show']);
Route::get('/profile/{username}/posts', [Guest\GuestProfileController::class, 'posts']);


// 兜底：访客前缀下任何未匹配的 GET 路径（如 followers/followings/info 等猜测路径）
// 一律返回 404 JSON，不泄露资源存在性，也不会落入 web 端 SPA 兜底；
// 仅捕获 GET（其余方法仍由路由系统返回 405）。
Route::get('{any?}', function () {
    return response()->json([
        'status'  => 'error',
        'code'    => 404,
        'message' => 'Not Found.',
    ], 404);
})
    ->name('guest-fallback')
    ->where('any', '.*');
