<?php

namespace App\Livewire\User\Onboarding;

use App\Enums\User\UserStatus;
use App\Events\User\Auth\UserSignupCompletedEvent;
use App\Livewire\User\Onboarding\Base\OnboardingBase;
use App\Models\User;
use App\Services\Relations\FollowService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class Username extends OnboardingBase
{
    public string $username;
    public string $password;
    public $isAvailable = null;
	public int $stepIndex;
    public array $stepData;

    public function mount()
    {
        $this->username = me()->username;
        $this->password = '';
    }

    public function render()
    {
        return view('livewire.user.onboarding.username');
    }

    public function checkAvailability()
    {
        if(Str::length($this->username) >= 1) {
            $this->isAvailable = (User::where('username', $this->username)->exists() != true);
        }
    }

    public function updatedUsername()
    {
        $this->reset('isAvailable');

        $this->checkAvailability();
    }

    public function submitForm()
    {
        $this->validate(rules: [
            'username' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z0-9._]+$/', Rule::unique('users', 'username')->ignore(me()->id)],
            'password' => ['nullable', Rule::requiredIf(config('user.require.password')), 'string', 'min:8', 'max:62']
        ], attributes: [
            'username' => __('labels.username'),
            'password' => __('labels.password'),
        ], messages: [
            'username.regex' => 'The username can only contain letters, numbers, underscores, and dots.',
        ]);

        $user = me();

        $randomPassword = Str::password(20);

        $user->updateQuietly([
            'username' => $this->username,
            'password' => (config('user.require.password')) ? bcrypt($this->password) : $randomPassword,
            'status' => UserStatus::ACTIVE
        ]);

        // 密码变更后必须重新登录：session 里存的 password_hash_web 还是
        // confirm-signup 时的旧哈希（bcrypt('')），Sanctum 的 AuthenticateSession
        // 中间件会在下一个 stateful API 请求比对哈希，不一致即强制登出并清空
        // session → SPA bootstrap 401 → 用户被甩到 bootstrap-error 页。
        // relogin 同时刷新 remember cookie（recaller 里同样嵌着旧哈希）。
        auth()->guard('web')->login($user, true);

        $this->makeFollowList();

        event(new UserSignupCompletedEvent(me()));

        $this->redirect($this->getNextStepRoute($this->stepIndex));
    }

    private function makeFollowList()
    {
        try {
            $followList = config('user.auto_follow_list');

            if(empty($followList)) {
                return false;
            }

            $followList = explode(',', $followList);

            $followList = User::active()->whereIn('username', $followList)->get();

            if($followList->isNotEmpty()) {
                foreach($followList as $userData) {
                    (new FollowService(me(), $userData))->follow();
                }
            }

            return true;
        } catch (Throwable $th) {
            logger()->error('Error making follow list on onboard.', [
                'error' => $th->getMessage(),
                'followList' => $followList
            ]);

            return false;
        }
    }
}
