<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function update(User $user, Post $postData) {
        // 原生广告影子帖：文案/素材只能从广告管理页修改，防止绕过审批直接改帖
        if(! empty($postData->ad_id)) {
            return false;
        }

        return $postData->user_id === $user->id || $user->isRoot();
    }

    public function delete(User $user, Post $postData) {
        // 原生广告影子帖：删除需走广告删除流程（连带预算/素材清理）
        if(! empty($postData->ad_id)) {
            return false;
        }

        return $postData->user_id === $user->id || $user->isRoot();
    }
}
