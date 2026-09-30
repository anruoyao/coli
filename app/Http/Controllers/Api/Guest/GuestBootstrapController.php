<?php

namespace App\Http\Controllers\Api\Guest;

use App\Info\ColibriPlus;
use App\Models\User;
use App\Settings\GuestSettings;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;

/**
 * 访客模式初始化。
 *
 * 单一公开入口同时服务两种状态：
 * - 未登录访客：auth.user=null + 访客能力矩阵（只读白名单）；
 * - 已登录用户（session）：返回与旧 /bootstrap/bootstrap 同构的用户数据，
 *   前端据此直接进入完整模式。
 */
class GuestBootstrapController extends GuestController
{
    use SupportsApiResponses;

    public function bootstrap(Request $request)
    {
        $me = $request->user();

        return $this->responseSuccess([
            'data' => [
                'version' => ColibriPlus::VERSION,
                'name' => config('app.name'),
                'author' => [
                    'name' => 'Mansur Terla. Full-Stack Web Developer.',
                    'email' => 'mansurtl.contact@gmail.com',
                ],
                'auth' => [
                    'status' => $me !== null,
                    'user' => $me ? $this->getUserData($me) : null,
                ],
                'guest' => [
                    'enabled' => app(GuestSettings::class)->enabled,
                    'capabilities' => $this->capabilities(),
                    'visible_nav' => ['feed', 'post', 'profile'],
                ],
            ],
        ]);
    }

    /**
     * 访客能力矩阵：访客仅拥有内容只读权限，写/互动/受限模块全部 false。
     * 前端以本矩阵渲染受控 UI 与登录引导。
     */
    private function capabilities(): array
    {
        return [
            'feed' => ['view' => true],
            'post' => [
                'view' => true,
                'create' => false,
                'edit' => false,
                'delete' => false,
                'react' => false,
                'report' => false,
            ],
            'comment' => [
                'view' => true,
                'create' => false,
                'delete' => false,
                'react' => false,
            ],
            'poll' => ['view' => true, 'vote' => false],
            'profile' => [
                'view' => true,
                'edit' => false,
                'view_followers' => false,
                'view_followings' => false,
            ],
            'follow' => false,
            'bookmark' => false,
            'messenger' => false,
            'stories' => false,
            'explore' => false,
            'marketplace' => false,
            'jobs' => false,
            'wallet' => false,
            'settings' => false,
        ];
    }

    /**
     * 用户数据结构与 BootstrapController::getUserData 保持一致（不含敏感字段）。
     */
    private function getUserData(User $me): array
    {
        $userData = [
            'id' => $me->id,
            'name' => $me->name,
            'avatar_url' => $me->avatar_url,
            'cover_url' => $me->cover_url,
            'first_name' => $me->first_name,
            'last_name' => $me->last_name,
            'caption' => $me->getCaption(),
            'username' => $me->username,
            'has_tips' => $me->has_tips,
            'tips' => $me->tips,
            'is_master_account' => $me->isMasterAccount(),
            'is_author' => $me->isAuthor(),
            'verification' => [
                'status' => $me->verified,
                'date' => $me->verified_at ? $me->verified_at->getIso() : null,
            ],
            'meta' => [
                'is_admin' => $me->isAdmin(),
                'is_root' => $me->isRoot(),
            ],
        ];

        if ($me->isAdmin() || $me->isRoot()) {
            $userData['meta']['admin'] = [
                'url' => route('admin.dash.index'),
            ];
        }

        return $userData;
    }
}
