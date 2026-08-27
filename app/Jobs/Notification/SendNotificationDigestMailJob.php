<?php

namespace App\Jobs\Notification;

use App\Models\User;
use App\Models\NotificationBatch;
use App\Services\Notification\DigestPayloadBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 发送单个用户的聚合 Digest 邮件。
 *
 * 处理调度命令选定的「已到期」聚合批次（按 ID），组装摘要邮件并发送，
 * 成功后删除对应批次。批次 ID 由调度命令精确圈定，规避调度延迟导致的窗口错位。
 */
class SendNotificationDigestMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @param array<int> $batchIds */
    public function __construct(
        public int $notifiableId,
        public array $batchIds,
    ) {
    }

    public function handle(): void
    {
        $user = User::find($this->notifiableId);

        if (! $user || ! $this->userAllowsAnyDigestType($user)) {
            return;
        }

        $batches = NotificationBatch::query()
            ->whereIn('id', $this->batchIds)
            ->orderByDesc('source_time')
            ->get();

        if ($batches->isEmpty()) {
            return;
        }

        $payload = (new DigestPayloadBuilder($user, $batches))->build();

        if (empty($payload['sections']) && empty($payload['followers'])) {
            return;
        }

        Mail::to($user->routeNotificationFor('mail'))
            ->locale($user->language)
            ->send(
                (new MailMessage())
                    ->subject($payload['subject'])
                    ->view('emails.user.notifications.digest', [
                        'user' => $user,
                        'payload' => $payload,
                    ])
            );

        // 发送成功后清理已聚合的批次
        NotificationBatch::whereIn('id', $batches->pluck('id'))->delete();
    }

    /**
     * 依据用户的邮件通知类型开关，判断是否至少有某一类聚合内容允许发送邮件。
     */
    private function userAllowsAnyDigestType(User $user): bool
    {
        $settings = $user->emailNotificationSettings;

        if (! $settings) {
            return false;
        }

        return (bool) ($settings->reactions
            || $settings->comments
            || $settings->followers
            || $settings->mentions);
    }
}