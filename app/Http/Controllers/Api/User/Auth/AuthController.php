<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus App 认证 API（为 Chatter Flutter App 对接新增）
|--------------------------------------------------------------------------
| 说明：colibri 网页注册走「邮箱确认 + onboarding」，无 API。
| 本控制器为 App 提供即注册即用（直接创建 ACTIVE 用户并返回 Sanctum token）。
| 原则：只增不改，不影响原有网页流程与数据表。
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\Api\User\Auth;

use App\Actions\User\CreateUserAction;
use App\Enums\User\UserStatus;
use App\Http\Controllers\Controller;
use App\Mail\User\Auth\ResetPasswordMail;
use App\Models\EmailConfirmation;
use App\Models\User;
use App\Services\Blacklist\BlacklistService;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class AuthController extends Controller
{
    use SupportsApiResponses;

    /**
     * App 注册：直接创建 ACTIVE 用户并返回 Sanctum token（即注册即登录）。
     *
     * @param Request $request body: first_name, last_name(可选), username, email, password, password_confirmation(可选), language(可选), device_name(可选)
     */
    public function register(Request $request)
    {
        $firstName   = $request->get('first_name', '');
        $lastName    = $request->get('last_name', '');
        $username    = $request->get('username', '');
        $email       = $request->get('email', '');
        $password    = $request->get('password', '');

        // 注册开关（数据库动态配置，与网页 Signup 一致）
        if (! config('features.registration.enabled')) {
            return $this->responseError([
                'message' => __('auth.registration_disabled')
            ], 403);
        }

        // 邮箱黑名单检查（与网页 Signup 一致）
        $blacklistService = app(BlacklistService::class);
        if ($blacklistService->isEmailBlacklisted($email)) {
            return $this->responseError([
                'message' => __('auth.email_blocked'),
                'errors'  => ['email' => [__('auth.email_blocked')]]
            ], 422);
        }

        // 一次性/临时邮箱域名拦截（防批量注册机）
        $emailDomain = mb_strtolower(mb_substr($email, mb_strpos($email, '@') + 1));
        if (in_array($emailDomain, config('security.disposable_email_domains', []), true)) {
            return $this->responseError([
                'message' => __('auth.email_blocked'),
                'errors'  => ['email' => [__('auth.email_blocked')]]
            ], 422);
        }

        $validator = Validator::make([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'username'   => $username,
            'email'      => $email,
            'password'   => $password,
        ], [
            'first_name' => ['bail', 'required', 'string', 'min:2', 'max:32'],
            'last_name'  => ['bail', 'nullable', 'string', 'min:2', 'max:32'],
            'username'   => ['bail', 'required', 'string', 'regex:/^[a-zA-Z0-9._]+$/', 'min:2', 'max:32', Rule::unique('users', 'username')],
            'email'      => ['bail', 'required', 'string', 'email', 'max:120', Rule::unique('users', 'email')],
            'password'   => ['bail', 'required', 'string', 'min:' . config('user.validation.password.min'), 'max:' . config('user.validation.password.max')],
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }

        // 复用 CreateUserAction 保证与网页注册一致的关联数据（钱包/隐私/通知设置等）
        $user = (new CreateUserAction([
            'username'         => $username,
            'first_name'       => $firstName,
            'last_name'        => $lastName,
            'email'            => $email,
            'password'         => $password,
            'status'           => UserStatus::ACTIVE,
            'email_verified_at'=> now(),
            'language'         => $request->get('language', config('user.language')),
        ]))->execute();

        $deviceName = $request->get('device_name', 'app');

        prune_user_tokens($user, (int) config('security.auth.max_tokens_per_account', 10));

        $token      = $user->createToken($deviceName)->plainTextToken;

        // 防御：若未填出生日期，则将生日隐私置为私密，避免「信息」页公开空生日/年龄（与网页轮一致）
        $hasBirthdate = ! empty($user->birth_day) && ! empty($user->birth_month) && ! empty($user->birth_year);
        if (! $hasBirthdate && $user->privacySettings) {
            $user->privacySettings()->update(['birthdate_privacy' => true]);
        }

        return $this->responseSuccess([
            'data' => [
                'token' => $token,
                'user'  => $this->formatUser($user),
            ]
        ], 201);
    }

    /**
     * App 忘记密码：发送重置邮件（复用网页 Forgot 逻辑）。
     *
     * @param Request $request body: email
     */
    public function forgotPassword(Request $request)
    {
        $email = $request->get('email', '');

        $validator = Validator::make([
            'email' => $email
        ], [
            'email' => ['required', 'string', 'email', 'max:62', 'exists:users,email']
        ], [
            'email.exists' => __('auth.email_not_found')
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }

        $emailToken = Str::uuid();

        EmailConfirmation::create([
            'email' => $email,
            'token' => $emailToken
        ]);

        $userData = User::where('email', $email)->first();

        try {
            Mail::to($email)->queue(new ResetPasswordMail([
                'name' => $userData->name,
                'link' => route('user.auth.reset', ['token' => $emailToken])
            ]));
        } catch (Throwable $e) {
            return $this->responseError([
                'message' => __('auth.email_send_failed')
            ], 500);
        }

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    /**
     * App 重置密码：用邮件中的 token 重置密码。
     *
     * @param Request $request body: token, email, password
     */
    public function resetPassword(Request $request)
    {
        $token    = $request->get('token', '');
        $email    = $request->get('email', '');
        $password = $request->get('password', '');

        $validator = Validator::make([
            'token'    => $token,
            'email'    => $email,
            'password' => $password,
        ], [
            'token'    => ['required', 'string', 'uuid'],
            'email'    => ['required', 'string', 'email', 'max:62'],
            'password' => ['required', 'string', 'min:' . config('user.validation.password.min'), 'max:' . config('user.validation.password.max')],
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }

        $confirmation = EmailConfirmation::where('token', $token)
            ->where('email', $email)
            ->first();

        if (! $confirmation) {
            return $this->responseError([
                'message' => __('auth.invalid_reset_token')
            ], 404);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return $this->responseError([
                'message' => __('auth.email_not_found')
            ], 404);
        }

        $user->update([
            'password' => bcrypt($password)
        ]);

        EmailConfirmation::where('email', $email)->delete();

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    /**
     * App 登出：注销当前访问令牌（token 版，替代网页 session 登出）。
     *
     * @param Request $request
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    /**
     * 用户摘要（结构对齐 /api/bootstrap 的 user 字段，便于 App 复用）。
     */
    private function formatUser(User $user)
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'first_name'  => $user->first_name,
            'last_name'   => $user->last_name,
            'username'    => $user->username,
            'caption'     => $user->getCaption(),
            'email'       => $user->email,
            'avatar_url'  => $user->avatar_url,
            'cover_url'   => $user->cover_url,
            'language'    => $user->language,
            'verified'    => (bool) $user->verified,
            'status'      => $user->status,
        ];
    }
}
