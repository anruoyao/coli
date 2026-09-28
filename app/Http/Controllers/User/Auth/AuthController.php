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

namespace App\Http\Controllers\User\Auth;

use App\Actions\User\CreateUserAction;
use App\Events\User\Auth\UserLoggedInEvent;
use App\Http\Controllers\Controller;
use App\Models\EmailConfirmation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function index()
    {
        return view('auth::index');
    }

    public function signup()
    {
        return view('auth::signup');
    }

    public function forgotPassword()
    {
        return view('auth::forgot');
    }

    public function resetPassword(string $token)
    {
        $confirmationData = $this->getTokenData($token);

        return view('auth::reset', [
            'confirmationData' => $confirmationData
        ]);
    }

    public function forgotSuccess(string $hash_id)
    {
        $confirmationData = $this->getTokenDataByHashId($hash_id);

        return view('auth::forgot-success', [
            'confirmationData' => $confirmationData
        ]);
    }

    public function signupSuccess(string $hashId)
    {
        $confirmationData = $this->getTokenDataByHashId($hashId);

        return view('auth::signup-success', [
            'confirmationData' => $confirmationData
        ]);
    }

    public function confirmSignup(string $token)
    {
        $confirmationData = $this->getTokenData($token);

        $tempUsername = Str::before($confirmationData->email, '@');

        $tempUsernameTaken = User::where('username', $tempUsername)->exists();

        if($tempUsernameTaken) {
            $emailDomain = Str::before(Str::after($confirmationData->email, '@'), '.');

            $tempUsername = "{$tempUsername}_{$emailDomain}";

            $tempUsernameTaken = User::where('username', $tempUsername)->exists();

            if($tempUsernameTaken) {

                $lastUserId = User::max('id');

                $lastUserId = (is_integer($lastUserId)) ? ($lastUserId + 1) : 1;

                $usernamePrefix = config('user.username_prefix');

                $tempUsername = "{$usernamePrefix}{$lastUserId}";
            }
        }

        $insertData = [
            'email' => $confirmationData->email,
            'username' => $tempUsername
        ];

        try {
            $newUser = (new CreateUserAction($insertData))->execute();
        } catch (QueryException $e) {
            // users 唯一索引兜底：确认链接被重放/页面重试并发撞上 1062 时，
            // 说明该邮箱已有账号 —— 消费掉 token 并引导登录，而非 500 引发继续重试
            if (intval($e->errorInfo[1] ?? 0) === 1062) {
                $confirmationData->delete();

                return redirect()->route('user.auth.index');
            }

            throw $e;
        }

        Auth::guard('web')->login($newUser, true);

        event(new UserLoggedInEvent(me()));

        $confirmationData->delete();

        return redirect()->route('user.onboarding.index', 'profile');
    }

    private function getTokenDataByHashId(string $hash_id)
    {
        return EmailConfirmation::whereHashId($hash_id)->firstOrFail();
    }

    private function getTokenData($token)
    {
        $validator = Validator::make([
            'token' => $token
        ], [
            'token' => ['required', 'string', 'uuid']
        ]);

        if($validator->fails()) {
            abort(404);
        }

        return EmailConfirmation::where('token', $token)->firstOrFail();
    }

    public function logout(Request $request)
    {
        auth()->guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();


        return redirect()->route('user.auth.index');
    }
}
