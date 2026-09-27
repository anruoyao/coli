<?php

namespace App\Console\Commands;

use App\Models\MarketingCampaign;
use Illuminate\Console\Command;
use App\Services\Marketing\CampaignService;

/**
 * 营销活动调度派发。
 *
 * 每分钟执行一次（cron: schedule:run）。扫描 sending 状态的活动，
 * 每 tick 认领一批收件人并派发对应 Job：邮件受 EmailRateLimiter 实时配额
 * 约束（动态限速 + 冷却），站内通知按 per-tick 上限削峰。
 * 天然错峰削峰，避免瞬时洪峰触发 QQ SMTP 官方限流。
 */
class MarketingSendCampaignsCommand extends Command
{
    protected $signature = 'marketing:send';

    protected $description = '扫描发送中的营销活动并分片派发邮件/站内通知 Job';

    public function handle(): int
    {
        if (! config('notifications.marketing.enabled', false)) {
            return self::SUCCESS;
        }

        $service = app(CampaignService::class);

        $campaigns = MarketingCampaign::query()
            ->where('status', MarketingCampaign::STATUS_SENDING)
            ->get();

        $dispatched = 0;

        foreach ($campaigns as $campaign) {
            $dispatched += $service->dispatchTick($campaign);
        }

        if ($dispatched > 0) {
            $this->info("Dispatched {$dispatched} marketing job(s).");
        }

        return self::SUCCESS;
    }
}