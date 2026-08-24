<?php

namespace App\Http\Controllers\Admin\User;

use App\Models\User;
use App\Enums\User\UserType;
use App\Enums\User\UserStatus;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Enums\User\ASRStatus;
use App\Http\Controllers\Controller;
use App\Actions\User\DeleteUserAction;
use App\Events\Admin\User\UserStatusChangedEvent;

class UserController extends Controller
{
    private $filters = [];

    public function index(Request $request)
    {
        $type = $request->string('type')->value;
        $type = in_array($type, ['all', 'author', 'reader']) ? $type : 'all';

        $this->filters = [
            'search' => $request->string('search')->value,
            'type' => $type,
        ];

        $users = User::onboarded()->when((! empty($this->filters['search'])), function ($query) {
            $query->where(function($query) {
                $query->where('first_name', 'like', "%{$this->filters['search']}%")
                ->orWhere('last_name', 'like', "%{$this->filters['search']}%")
                ->orWhere('username', 'like', "%{$this->filters['search']}%")
                ->orWhere('bio', 'like', "%{$this->filters['search']}%")
                ->orWhere('email', 'like', "%{$this->filters['search']}%");
            });
        })->when(($this->filters['type'] != 'all'), function ($query) {
            $query->where('type', ($this->filters['type'] == 'author') ? UserType::AUTHOR : UserType::READER);
        })->latest()->paginate(10);

        return view('admin::users.index.index', [
            'users' => $users,
            'filters' => $this->filters,
        ]);
    }

    public function show(int $userId)
    {
        $userData = $this->fetchUserById($userId);

        $authorshipRequest = $userData->authorshipRequest()->pending()->first();

        return view('admin::users.show.index', [
            'userData' => $userData,
            'authorshipRequest' => $authorshipRequest
        ]);
    }

    public function wallet(int $userId)
    {
        $userData = $this->fetchUserById($userId);

        return view('admin::users.wallet.index', [
            'userData' => $userData
        ]);
    }

    public function destroy(int $userId)
    {
        $userData = $this->fetchUserById($userId);

        if(me()->id == $userData->id) {
            return redirect()->route('admin.users.index')->with('flashMessage', (new Flash(content: __('admin/flash.user.delete_self')))->get());
        }

        (new DeleteUserAction($userData))->execute();

        return redirect()->route('admin.users.index')->with('flashMessage', (new Flash(content: __('admin/flash.user.delete_success')))->get());
    }

    public function authorize(int $userId)
    {
        $userData = $this->fetchUserById($userId);

        $userData->update([
            'type' => UserType::AUTHOR,
        ]);

        $userData->authorshipRequest()->delete();

        return redirect()->route('admin.users.show', $userId)->with('flashMessage', (new Flash(content: __('admin/flash.user.authorize_success')))->get());
    }

    public function unauthorize(int $userId)
    {
        $userData = $this->fetchUserById($userId);

        $userData->update([
            'type' => UserType::READER,
        ]);

        return redirect()->route('admin.users.show', $userId)->with('flashMessage', (new Flash(content: __('admin/flash.user.unauthorize_success')))->get());
    }

    public function rejectAuthorization(int $userId)
    {
        $userData = $this->fetchUserById($userId);

        $userData->update([
            'type' => UserType::READER,
        ]);

        $userData->authorshipRequest()->update([
            'status' => ASRStatus::REJECTED
        ]);

        return redirect()->route('admin.users.show', $userId)->with('flashMessage', (new Flash(content: __('admin/flash.user.authorize_reject')))->get());
    }

    public function verify(int $userId)
    {
        $userData = $this->fetchUserById($userId);

        $userData->update([
            'verified' => true,
        ]);

        return redirect()->route('admin.users.show', $userId)->with('flashMessage', (new Flash(content: __('admin/flash.user.verify_success')))->get());
    }

    public function unverify(int $userId)
    {
        $userData = $this->fetchUserById($userId);

        $userData->update([
            'verified' => false,
        ]);

        return redirect()->route('admin.users.show', $userId)->with('flashMessage', (new Flash(content: __('admin/flash.user.unverify_success')))->get());
    }

    /**
     * 封禁用户：status=blocked + 原因，删除该用户全部 Sanctum token（强制下线），
     * 并通过 Reverb 私有频道推送 main.command(banned) 让在线 App 秒级进入封禁页。
     */
    public function block(Request $request, int $userId)
    {
        $userData = $this->fetchUserById($userId);

        if (me()->id == $userData->id || $userData->isRoot() || $userData->isAdmin()) {
            return redirect()->route('admin.users.show', $userId)->with('flashMessage', (new Flash(content: __('admin/flash.user.block_self')))->get());
        }

        $reason = $request->string('reason')->value;

        $userData->update([
            'status' => UserStatus::BLOCKED,
            'status_reason' => $reason ?: null,
        ]);

        // 清除全部会话 token，强制已登录设备失效
        $userData->tokens()->delete();

        event(new UserStatusChangedEvent($userData, UserStatus::BLOCKED, $reason));

        return redirect()->route('admin.users.show', $userId)->with('flashMessage', (new Flash(content: __('admin/flash.user.block_success')))->get());
    }

    /**
     * 解封用户：status=active，清空原因，广播 main.command(active) 恢复在线 App。
     */
    public function unblock(int $userId)
    {
        $userData = $this->fetchUserById($userId);

        $userData->update([
            'status' => UserStatus::ACTIVE,
            'status_reason' => null,
        ]);

        event(new UserStatusChangedEvent($userData, UserStatus::ACTIVE, null));

        return redirect()->route('admin.users.show', $userId)->with('flashMessage', (new Flash(content: __('admin/flash.user.unblock_success')))->get());
    }

    private function fetchUserById(int $userId)
    {
        return User::onboarded()->findOrFail($userId);
    }
}
