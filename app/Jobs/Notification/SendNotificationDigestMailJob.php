<?php

namespace App\Jobs\Notification;

use App\Models\User;
use App\Models\NotificationBatch;
use App\Services\Notification\DigestPayloadBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 发送单个用户的 1 小时聚合 Digest 邮件。
 *
 * 读取窗口（windowEnd - windowMinutes ~ windowEnd）内该用户的全部聚合批次，
 * 按帖子/评论实体分组组装摘要，尊重用户的邮件通知类型开关，发送成功后删除批次。
 */
class SendNotificationDigestMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $notifiableId,
        public Carbon $windowEnd,
    ) {
    }

    public function handle(): void
    {
        $user = User::find($this->notifiableId);

        if (! $user || ! $this->userAllowsAnyDigestType($user)) {
            return;
        }

        $windowMinutes = (int) config('notifications.digest.window_minutes', 60);
        $windowStart = $this->windowEnd->copy()->subMinutes($windowMinutes);
        $types = (array) config('notifications.digest.types', []);

        $batches = NotificationBatch::query()
            ->where('notifiable_id', $user->id)
            ->whereIn('type', $types)
            ->where('source_time', '>=', $windowStart)
            ->where('source_time', '<=', $this->windowEnd)
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