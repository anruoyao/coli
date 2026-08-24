<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Social Network Web Application.
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

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Enums\User\UserStatus;
use Symfony\Component\HttpFoundation\Response;

class UserStatusMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if(auth_check()) {
            if(me()->status == UserStatus::ONBOARDING) {
                return redirect()->route('user.onboarding.index', 'profile');
            }

            if(in_array(me()->status, [UserStatus::BLOCKED, UserStatus::SUSPENDED])) {
                // API 请求：返回 JSON + 状态头，供 App 识别并跳封禁页
                if($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 403,
                        'message' => __('api/auth.user_status_' . me()->status->value),
                        'data' => [
                            'user_status' => me()->status->value,
                            'reason' => me()->status_reason,
                        ],
                    ], 403, [
                        'X-User-Status' => me()->status->value,
                    ]);
                }

                abort(403);
            }
        }

        return $next($request);
    }
}