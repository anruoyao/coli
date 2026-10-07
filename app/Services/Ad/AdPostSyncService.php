<?php

namespace App\Services\Ad;

use App\Models\Ad;
use App\Models\Post;
use App\Enums\Post\PostType;
use App\Enums\Post\PostStatus;
use App\Enums\Ad\AdStatus;
use App\Enums\Ad\AdApproval;

/**
 * 原生广告影子帖生命周期同步。
 *
 * 广告以帖子形态混入信息流：发布时自动创建一条关联帖子（posts.ad_id），
 * 点赞/评论/收藏等互动能力全部复用现有帖子体系。
 *
 * 状态机（应用层保证，FK cascade 仅作删库兜底）：
 * - PUBLISHED + APPROVED → 帖子 ACTIVE（不存在则创建，存在则同步文案与状态）
 * - PUBLISHED/PAUSED + PENDING → 帖子 DRAFT（可恢复的软隐藏）
 * - 任意 + REJECTED / COMPLETED → 帖子 DELETED（软删除，评论数据保留）
 * - 广告被删除 → 由 DeleteAdAction 走 DeletePostAction 物理清理
 */
class AdPostSyncService
{
    /**
     * 将影子帖状态与广告对齐（幂等，可在任意生命周期入口调用）。
     */
    public function sync(Ad $ad): void
    {
        $ad = $ad->refresh();

        $post = $ad->post()->first();

        if ($this->shouldBeVisible($ad)) {
            if (empty($post)) {
                $this->createShadowPost($ad);
            } else {
                $this->syncShadowPost($ad, $post);
            }
        } elseif (! empty($post)) {
            $post->update([
                'status' => $this->isTerminalState($ad) ? PostStatus::DELETED : PostStatus::DRAFT,
            ]);
        }
    }

    private function shouldBeVisible(Ad $ad): bool
    {
        return $ad->status->isPublished() && $ad->approval->isApproved();
    }

    // REJECTED / COMPLETED 属于终态：影子帖直接软删除而非软隐藏
    private function isTerminalState(Ad $ad): bool
    {
        return $ad->approval->isRejected() || $ad->status === AdStatus::COMPLETED;
    }

    private function createShadowPost(Ad $ad): void
    {
        Post::create([
            'user_id' => $ad->user_id,
            'ad_id' => $ad->id,
            'content' => $this->composeContent($ad),
            'type' => PostType::IMAGE,
            'status' => PostStatus::ACTIVE,
        ]);
    }

    private function syncShadowPost(Ad $ad, Post $post): void
    {
        $post->update([
            'content' => $this->composeContent($ad),
            'status' => PostStatus::ACTIVE,
        ]);
    }

    /**
     * 帖子正文 = 加粗标题 + 空行 + 广告文案（posts.title 字段卡片不渲染，故并入正文）。
     *
     * 广告文案入库时已被 e() 转义，这里先解码还原为纯文本，
     * 使影子帖与普通帖一致（DB 存原文，Resource 输出时统一转义）。
     */
    private function composeContent(Ad $ad): string
    {
        $title = html_entity_decode((string) $ad->title, ENT_QUOTES, 'UTF-8');
        $content = html_entity_decode((string) $ad->content, ENT_QUOTES, 'UTF-8');

        if (empty($title)) {
            return $content;
        }

        return sprintf("**%s**\n\n%s", $title, $content);
    }
}
