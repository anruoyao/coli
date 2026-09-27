<?php

namespace App\Services\Marketing;

use App\Models\User;
use App\Models\Post;
use App\Enums\User\UserType;
use App\Models\MarketingCampaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\MarketingCampaignRecipient;
use App\Jobs\Marketing\SendMarketingEmailJob;
use App\Jobs\Marketing\SendMarketingInAppNotificationJob;

/**
 * 营销活动编排服务。
 *
 * 职责：
 *  - 圈定目标用户（全部已开启用户 / 手动用户名列表 / 按用户类型）；
 *  - 生成收件人快照（邮件与站内分通道独立状态机）；
 *  - 认领并发（pending → queued + 派发 Job）；
 *  - 完成后收尾（sending → completed）。
 *
 * 邮件派发量由 EmailRateLimiter 实时配额约束（dispatchTick 粗粒度 + Job 内
 * acquire 精确控速双层防过载）；站内通知按 dispatch.in_app_per_tick 削峰。
 */
class CampaignService
{
    public function __construct(
        protected EmailRateLimiter $rateLimiter,
    ) {
    }

    // ------------------------------------------------------------------
    // 目标圈定
    // ------------------------------------------------------------------
    /**
     * 解析活动目标，返回收件人候选列表：
     *  ['user_id' => ?int, 'email' => ?string]
     *
     * - all：全部用户（user_id + email）
     * - type：按用户类型筛选用户
     * - manual：逐条识别 —— 数字 → 用户ID；合法邮箱 → 匹配用户邮箱，无账号时作为
     *   「原始邮箱收件人」（user_id=null，仅走邮件通道）；其它 → 按用户名识别。
     */
    public function resolveTargets(MarketingCampaign $campaign): array
    {
        $targets = match ($campaign->target_type) {
            MarketingCampaign::TARGET_MANUAL => $this->resolveManualTargets($campaign),
            MarketingCampaign::TARGET_TYPE => $this->resolveTypeTargets($campaign),
            default => $this->resolveAllTargets(),
        };

        // 去重（同一用户或同一邮箱只保留一条）
        $seen = [];
        $unique = [];

        foreach ($targets as $target) {
            $key = $target['user_id'] !== null ? 'u'.$target['user_id'] : 'e'.$target['email'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $target;
        }

        return $unique;
    }

    protected function resolveAllTargets(): array
    {
        return User::query()
            ->get(['id', 'email'])
            ->map(fn (User $user) => [
                'user_id' => $user->id,
                'email' => $user->email,
            ])
            ->all();
    }

    protected function resolveTypeTargets(MarketingCampaign $campaign): array
    {
        $type = $campaign->target_user_type;

        if (! in_array($type, [UserType::AUTHOR->value, UserType::READER->value], true)) {
            return [];
        }

        return User::query()
            ->where('type', $type)
            ->get(['id', 'email'])
            ->map(fn (User $user) => [
                'user_id' => $user->id,
                'email' => $user->email,
            ])
            ->all();
    }

    protected function resolveManualTargets(MarketingCampaign $campaign): array
    {
        $targets = [];

        foreach ((array) ($campaign->target_user_ids ?? []) as $identifier) {
            $identifier = trim((string) $identifier);

            if ($identifier === '') {
                continue;
            }

            if (ctype_digit($identifier)) {
                $targets[] = [
                    'user_id' => (int) $identifier,
                    'email' => User::whereKey((int) $identifier)->value('email'),
                ];

                continue;
            }

            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $user = User::where('email', $identifier)->first(['id', 'email']);

                $targets[] = $user
                    ? ['user_id' => $user->id, 'email' => $user->email]
                    : ['user_id' => null, 'email' => $identifier]; // 原始邮箱收件人

                continue;
            }

            $user = User::where('username', $identifier)->first(['id', 'email']);

            if ($user) {
                $targets[] = ['user_id' => $user->id, 'email' => $user->email];
            }
        }

        return $targets;
    }

    // ------------------------------------------------------------------
    // 收件人快照
    // ------------------------------------------------------------------
    public function buildRecipients(MarketingCampaign $campaign): int
    {
        if (! in_array($campaign->status, [MarketingCampaign::STATUS_DRAFT, MarketingCampaign::STATUS_CANCELLED], true)) {
            return 0;
        }

        if ($campaign->recipients()->count() > 0) {
            return 0;
        }

        $targets = $this->resolveTargets($campaign);

        $created = 0;

        DB::transaction(function () use ($campaign, $targets, &$created) {
            foreach ($targets as $target) {
                $userId = $target['user_id'];
                $email = $target['email'];
                $hasEmail = filled($email);

                MarketingCampaignRecipient::query()->insertOrIgnore([
                    'campaign_id' => $campaign->id,
                    'user_id' => $userId,
                    'email' => $email,
                    'email_status' => $campaign->email_enabled && $hasEmail
                        ? MarketingCampaignRecipient::EMAIL_PENDING
                        : MarketingCampaignRecipient::EMAIL_SKIPPED,
                    'email_error' => $campaign->email_enabled && ! $hasEmail ? 'no_email' : null,
                    'in_app_status' => $campaign->in_app_enabled && $userId !== null
                        ? MarketingCampaignRecipient::INAP_PENDING
                        : MarketingCampaignRecipient::INAP_SKIPPED,
                    'in_app_error' => $campaign->in_app_enabled && $userId === null ? 'no_user' : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created++;
            }
        });

        $campaign->update([
            'email_recipient_count' => $campaign->email_enabled
                ? $campaign->recipients()->whereNotNull('email')->count()
                : 0,
            'in_app_recipient_count' => $campaign->in_app_enabled
                ? $campaign->recipients()->whereNotNull('user_id')->count()
                : 0,
        ]);

        return $created;
    }

    // ------------------------------------------------------------------
    // 活动启动
    // ------------------------------------------------------------------
    public function start(MarketingCampaign $campaign, bool $dispatchImmediately = true): void
    {
        if (! in_array($campaign->status, [MarketingCampaign::STATUS_DRAFT, MarketingCampaign::STATUS_CANCELLED], true)) {
            return;
        }

        // 注意顺序：收件人快照必须在状态切换为 sending 之前生成
        // （buildRecipients 仅允许 draft/cancelled 状态写入）
        $this->buildRecipients($campaign);

        $campaign->update([
            'status' => MarketingCampaign::STATUS_SENDING,
            'started_at' => now(),
        ]);

        if ($dispatchImmediately) {
            $this->dispatchTick($campaign);
        }
    }

    public function cancel(MarketingCampaign $campaign): void
    {
        if ($campaign->status !== MarketingCampaign::STATUS_SENDING) {
            return;
        }

        $campaign->update(['status' => MarketingCampaign::STATUS_CANCELLED]);
    }

    // ------------------------------------------------------------------
    // 关联帖子快照
    // ------------------------------------------------------------------
    /**
     * 构建活动关联帖子的展示快照（发送时定格，写入通知 data / 邮件数据）。
     *
     * - 仅保留仍在展示状态（active）的帖子，被删/隐藏的自动过滤；
     * - 保持创建时的顺序，最多取 MAX_POSTS 个；
     * - excerpt/封面/作者/统计均在此时定格，后续帖子编辑不影响已发通知。
     */
    public function buildPostSnapshots(?array $postIds): array
    {
        $postIds = collect($postIds ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->take(MarketingCampaign::MAX_POSTS)
            ->values();

        if ($postIds->isEmpty()) {
            return [];
        }

        $posts = Post::query()
            ->active()
            ->with(['user:id,first_name,last_name,username,avatar', 'media'])
            ->whereIn('id', $postIds->all())
            ->get()
            ->sortBy(fn (Post $post) => array_search($post->id, $postIds->all(), true))
            ->values();

        return $posts->map(fn (Post $post) => $this->buildPostSnapshot($post))->all();
    }

    protected function buildPostSnapshot(Post $post): array
    {
        $cover = $post->media
            ->first(fn ($media) => $media->type->isImage() && $media->status->isProcessed());

        $reactionsCount = $post->reactions()->count();
        $commentsCount = (int) ($post->comments_count ?: $post->comments()->count());

        return [
            'id' => $post->id,
            'hash_id' => $post->hashid,
            'url' => $post->url,
            'excerpt' => Str::limit(trim((string) $post->content), 80),
            'cover_url' => $cover?->thumbnail_url ?: $cover?->source_url,
            'author_name' => $post->user?->name,
            'author_avatar' => $post->user?->avatar_url,
            'reactions_count' => $reactionsCount,
            'comments_count' => $commentsCount,
        ];
    }

    // ------------------------------------------------------------------
    // 分分片派发（供调度命令与手动启动调用）
    // ------------------------------------------------------------------
    public function dispatchTick(MarketingCampaign $campaign): int
    {
        if ($campaign->status !== MarketingCampaign::STATUS_SENDING) {
            return 0;
        }

        // 自愈：回收卡在 queued 超过阈值的收件人（Job 被队列丢失/worker 中断等场景），
        // 回收后同 tick 内按 pending 正常认领重派
        $this->reclaimStaleQueued($campaign);

        $dispatched = 0;

        if ($campaign->email_enabled && config('notifications.marketing.email.enabled', false)) {
            $dispatched += $this->dispatchEmailRecipients($campaign);
        }

        if ($campaign->in_app_enabled && config('notifications.marketing.in_app_enabled', true)) {
            $dispatched += $this->dispatchInAppRecipients($campaign);
        }

        $this->finalizeIfDone($campaign);

        return $dispatched;
    }

    /**
     * 回收「假在途」收件人：状态为 queued 但超过 stale_queued_minutes 分钟
     * 仍无进展的，视为 Job 已丢失（worker 中断 / Redis 异常 / 进程被杀），
     * 重置回 pending 由本轮 dispatchTick 重新认领派发。
     *
     * 阈值需大于 Job 最长合法重试周期（限流退避 ~50 分钟），避免对仍在途的
     * Job 造成重复发送；默认 60 分钟，可用 MARKETING_STALE_QUEUED_MINUTES 调整。
     */
    protected function reclaimStaleQueued(MarketingCampaign $campaign): int
    {
        $minutes = (int) config('notifications.marketing.dispatch.stale_queued_minutes', 60);

        if ($minutes <= 0) {
            return 0;
        }

        $cutoff = now()->subMinutes($minutes);

        $reclaimed = $campaign->recipients()
            ->where('email_status', MarketingCampaignRecipient::EMAIL_QUEUED)
            ->where('updated_at', '<', $cutoff)
            ->update([
                'email_status' => MarketingCampaignRecipient::EMAIL_PENDING,
                'email_error' => 'reclaimed_stale',
                'updated_at' => now(),
            ]);

        $reclaimed += $campaign->recipients()
            ->where('in_app_status', MarketingCampaignRecipient::INAP_QUEUED)
            ->where('updated_at', '<', $cutoff)
            ->update([
                'in_app_status' => MarketingCampaignRecipient::INAP_PENDING,
                'in_app_error' => 'reclaimed_stale',
                'updated_at' => now(),
            ]);

        if ($reclaimed > 0) {
            \Illuminate\Support\Facades\Log::warning('Marketing recipients reclaimed as stale', [
                'campaign_id' => $campaign->id,
                'reclaimed' => $reclaimed,
            ]);
        }

        return $reclaimed;
    }

    protected function dispatchEmailRecipients(MarketingCampaign $campaign): int
    {
        // 以限速器剩余配额为硬上限（动态配额实时生效），再与 per_tick 取小
        $budget = min(
            (int) config('notifications.marketing.dispatch.per_tick', 50),
            max(0, $this->rateLimiter->availableBudget()),
        );

        if ($budget <= 0) {
            return 0;
        }

        $ids = $campaign->recipients()
            ->where('email_status', MarketingCampaignRecipient::EMAIL_PENDING)
            ->orderBy('id')
            ->limit($budget)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $campaign->recipients()->whereIn('id', $ids)->update(['email_status' => MarketingCampaignRecipient::EMAIL_QUEUED]);

        // 帖子快照对同一活动恒定（发送时定格），每批构建一次随 Job 传递，避免每收件人重复查询
        $postSnapshots = $this->buildPostSnapshots($campaign->post_ids);

        foreach ($ids as $recipientId) {
            SendMarketingEmailJob::dispatch($campaign->id, $recipientId, $postSnapshots);
        }

        return $ids->count();
    }

    protected function dispatchInAppRecipients(MarketingCampaign $campaign): int
    {
        $limit = (int) config('notifications.marketing.dispatch.in_app_per_tick', 200);

        $ids = $campaign->recipients()
            ->where('in_app_status', MarketingCampaignRecipient::INAP_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $campaign->recipients()->whereIn('id', $ids)->update(['in_app_status' => MarketingCampaignRecipient::INAP_QUEUED]);

        // 同邮件通道：每批构建一次帖子快照随 Job 传递
        $postSnapshots = $this->buildPostSnapshots($campaign->post_ids);

        foreach ($ids as $recipientId) {
            SendMarketingInAppNotificationJob::dispatch($campaign->id, $recipientId, $postSnapshots);
        }

        return $ids->count();
    }

    /**
     * 全部收件人处理完毕后收尾（completed）。
     */
    public function finalizeIfDone(MarketingCampaign $campaign): void
    {
        if ($campaign->status !== MarketingCampaign::STATUS_SENDING) {
            return;
        }

        $emailDone = $campaign->recipients()
            ->whereIn('email_status', [MarketingCampaignRecipient::EMAIL_PENDING, MarketingCampaignRecipient::EMAIL_QUEUED])
            ->exists() === false;

        $inAppDone = $campaign->recipients()
            ->whereIn('in_app_status', [MarketingCampaignRecipient::INAP_PENDING, MarketingCampaignRecipient::INAP_QUEUED])
            ->exists() === false;

        if ($emailDone && $inAppDone) {
            $campaign->update([
                'status' => MarketingCampaign::STATUS_COMPLETED,
                'finished_at' => now(),
            ]);
        }
    }
}