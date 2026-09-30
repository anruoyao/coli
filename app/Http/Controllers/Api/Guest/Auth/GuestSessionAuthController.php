<?php

namespace App\Http\Controllers\Api\Guest\Auth;

use App\Enums\User\UserStatus;
use App\Events\User\Auth\UserLoggedInEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Settings\AuthSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 访客面板内嵌登录（XHR 建立 web session）。
 *
 * 与现有登录界面共用同一套认证规则（throttle:login 限流、封禁状态、登录开关），
 * 成功后返回登录用户数据，SPA 无刷新切换为完整模式。
 *
 * 注意：本端点不属于只读访客路由组（访客路由仅 GET），
 * 挂载在公开 auth 前缀组，复用既有安全中间件。
 */
class GuestSessionAuthController extends Controller
{
    public function login(Request $request)
    {
        if (! app(AuthSettings::class)->login_enabled) {
            return response()->json([
                'status'  => 'error',
                'code'    => 403,
                'message' => __('auth.login_disabled'),
            ], 403);
        }

        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:62'],
            'password' => ['required', 'string', 'max:62'],
        ], attributes: [
            'login' => __('auth.login_or_email'),
            'password' => __('auth.password_label'),
        ]);

        $user = User::where('email', $credentials['login'])
            ->orWhere('username', $credentials['login'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => [__('auth.failed')],
            ]);
        }

        if (in_array($user->status, [UserStatus::BLOCKED, UserStatus::SUSPENDED], true)) {
            return response()->json([
                'status'  => 'error',
                'code'    => 403,
                'message' => __('api/auth/user_status_' . $user->status->value),
                'data' => [
                    'user_status' => $user->status->value,
                    'reason' => $user->status_reason,
                ],
            ], 403);
        }

        Auth::guard('web')->login($user, true);

        // 防会话固定：登录成功后重新生成 session（CSRF token 随之更新，
        // 客户端 axios 会自动读取新的 XSRF-TOKEN Cookie）。
        $request->session()->regenerate();

        event(new UserLoggedInEvent($user));

        return response()->json([
            'status'  => 'success',
            'message' => 'Authenticated.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar_url' => $user->avatar_url,
                    'cover_url' => $user->cover_url,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'caption' => $user->getCaption(),
                    'username' => $user->username,
                    'has_tips' => $user->has_tips,
                    'tips' => $user->tips,
                    'is_master_account' => $user->isMasterAccount(),
                    'is_author' => $user->isAuthor(),
                    'verification' => [
                        'status' => $user->verified,
                        'date' => $user->verified_at ? $user->verified_at->getIso() : null,
                    ],
                    'meta' => [
                        'is_admin' => $user->isAdmin(),
                        'is_root' => $user->isRoot(),
                    ],
                ],
            ],
        ]);
    }
}
