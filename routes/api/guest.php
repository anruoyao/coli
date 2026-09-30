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
