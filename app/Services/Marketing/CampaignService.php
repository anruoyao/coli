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
    public function resolveTargetUserIds(MarketingCampaign $campaign): array
    {
        $userIds = match ($campaign->target_type) {
            MarketingCampaign::TARGET_MANUAL => $this->resolveManualUserIds($campaign),
            MarketingCampaign::TARGET_TYPE => $this->resolveTypeUserIds($campaign),
            default => User::query()->pluck('id')->all(),
        };

        return array_values(array_unique(array_map('intval', $userIds)));
    }

    protected function resolveManualUserIds(MarketingCampaign $campaign): array
    {
        $identifiers = (array) ($campaign->target_user_ids ?? []);

        $usernames = collect($identifiers)->filter(fn ($id) => $id !== null && ! ctype_digit((string) $id));

        $byUsername = User::query()
            ->whereIn('username', $usernames->all())
            ->pluck('id')
            ->all();

        $byId = collect($identifiers)->filter(fn ($id) => $id !== null && ctype_digit((string) $id));

        $directIds = $byId->map(fn ($id) => (int) $id)->all();

        return array_merge($byUsername, $directIds);
    }

    protected function resolveTypeUserIds(MarketingCampaign $campaign): array
    {
        $type = $campaign->target_user_type;

        if (! in_array($type, [UserType::AUTHOR->value, UserType::READER->value], true)) {
            return [];
        }

        return User::query()->where('type', $type)->pluck('id')->all();
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

        $userIds = $this->resolveTargetUserIds($campaign);

        $created = 0;

        DB::transaction(function () use ($campaign, $userIds, &$created) {
            $users = User::query()->whereIn('id', $userIds)->get(['id', 'email']);

            foreach ($users as $user) {
                $hasEmail = filled($user->email);

                MarketingCampaignRecipient::query()->insertOrIgnore([
                    'campaign_id' => $campaign->id,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'email_status' => $campaign->email_enabled && $hasEmail
                        ? MarketingCampaignRecipient::EMAIL_PENDING
                        : MarketingCampaignRecipient::EMAIL_SKIPPED,
                    'email_error' => $campaign->email_enabled && ! $hasEmail ? 'no_email' : null,
                    'in_app_status' => $campaign->in_app_enabled
                        ? MarketingCampaignRecipient::INAP_PENDING
                        : MarketingCampaignRecipient::INAP_SKIPPED,
                    'in_app_error' => $campaign->in_app_enabled ? null : 'channel_disabled',
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
                ? $campaign->recipients()->count()
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

        $campaign->update([
            'status' => MarketingCampaign::STATUS_SENDING,
            'started_at' => now(),
        ]);

        $this->buildRecipients($campaign);

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