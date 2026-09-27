<?php

namespace App\Services\Marketing;

use App\Models\User;
use App\Enums\User\UserType;
use App\Models\MarketingCampaign;
use Illuminate\Support\Facades\DB;
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
    // 分分片派发（供调度命令与手动启动调用）
    // ------------------------------------------------------------------
    public function dispatchTick(MarketingCampaign $campaign): int
    {
        if ($campaign->status !== MarketingCampaign::STATUS_SENDING) {
            return 0;
        }

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

        foreach ($ids as $recipientId) {
            SendMarketingEmailJob::dispatch($campaign->id, $recipientId);
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

        foreach ($ids as $recipientId) {
            SendMarketingInAppNotificationJob::dispatch($campaign->id, $recipientId);
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