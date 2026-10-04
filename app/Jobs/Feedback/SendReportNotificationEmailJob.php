<?php

namespace App\Jobs\Feedback;

use Throwable;
use Exception;
use App\Models\Report;
use Illuminate\Bus\Queueable;
use App\Mail\Admin\ReportNotificationMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\ReportNotificationEmail;
use App\Models\ReportEmailLog;
use App\Settings\ReportNotificationSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * 举报管理员邮件通知 Job（database 队列异步执行）。
 *
 * - 逐个收件邮箱独立发送；单邮箱失败不影响其它邮箱
 * - 单个邮箱最多重试 3 次（Job tries=3 + 已发送跳过，重试只补发失败者）
 * - 每次投递写入 report_email_logs（收件邮箱/状态/尝试次数/错误/发送时间）
 */
class SendReportNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> 重试退避（秒） */
    public array $backoff = [60, 300];

    public function __construct(
        public int $reportId
    ) {
    }

    public function handle(): void
    {
        $report = Report::with('reporter')->find($this->reportId);

        if(! $report || ! $report->reporter) {
            return; // 举报已被删除：静默结束
        }

        // 发送时刻校验总开关（尊重发送时刻的最新配置）
        if(! app(ReportNotificationSettings::class)->enabled) {
            return;
        }

        $recipients = ReportNotificationEmail::enabled()->get();

        if($recipients->isEmpty()) {
            return;
        }

        $payload = $this->buildPayload($report);
        $subject = $this->buildSubject($report, $payload);

        $failed = [];

        foreach($recipients as $recipient) {
            // 幂等：同一举报同一邮箱只保留一条日志；已成功的跳过（重试只补发失败者）。
            // recipient_email 为加密存储，无法参与 where 查询，故以明文 sha1 哈希作幂等键。
            $log = ReportEmailLog::firstOrCreate(
                [
                    'report_id' => $report->id,
                    'recipient_hash' => sha1($recipient->email),
                ],
                [
                    'recipient_email' => $recipient->email,
                    'recipient_hash' => sha1($recipient->email),
                    'status' => ReportEmailLog::STATUS_PENDING,
                    'attempts' => 0,
                ]
            );

            if($log->status === ReportEmailLog::STATUS_SENT) {
                continue;
            }

            try {
                Mail::to($recipient->email)->send(
                    new ReportNotificationMail($subject, $payload)
                );

                $log->markSent();
            } catch (Throwable $e) {
                $log->markFailed($e->getMessage());

                $failed[] = $recipient->email;

                Log::error('Report notification email send failed', [
                    'report_id' => $report->id,
                    'recipient' => $recipient->email,
                    'attempt' => $log->attempts,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 存在失败收件人：抛出异常触发队列重试（已成功者在下一轮自动跳过）
        if($failed !== []) {
            throw new Exception('Report notification email failed for: ' . implode(', ', $failed));
        }
    }

    /**
     * 组装邮件载荷：举报时间 / 举报人 / 被举报内容 / 理由 / 补充说明 / 证据媒体。
     */
    private function buildPayload(Report $report): array
    {
        $adminLocale = (string) config('app.admin_locale', 'en');
        $reportable = $report->reportable;

        // 举报理由（后台语言）
        $reasonData = (new \App\Services\Feedback\ReportService($report->type->value, $adminLocale))->getReasons();
        $reason = $reasonData['reasons'][$report->reason_index] ?? [];

        // 被举报目标信息
        $target = [
            'id' => $report->reportable_id,
            'type_label' => $report->type->label(),
            'url' => url('/'),
            'preview' => null,
        ];

        if($reportable) {
            $target['url'] = match (true) {
                $reportable instanceof \App\Models\Post => $reportable->url,
                $reportable instanceof \App\Models\User => url("@{$reportable->username}"),
                $reportable instanceof \App\Models\Group => url("group/{$reportable->id}"),
                default => url('/'),
            };

            if($reportable instanceof \App\Models\Post) {
                $target['preview'] = mb_substr(trim(strip_tags((string) $reportable->content)), 0, 300) ?: null;
            } elseif($reportable instanceof \App\Models\User) {
                $target['preview'] = '@' . $reportable->username;
            } elseif($reportable instanceof \App\Models\Group) {
                $target['preview'] = (string) $reportable->name;
            }
        }

        // 证据媒体：被举报帖子的图片缩略图（如有）
        $media = [];

        if($reportable instanceof \App\Models\Post) {
            $media = $reportable->media()
                ->where('type', \App\Enums\Media\MediaType::IMAGE)
                ->limit(4)
                ->get()
                ->map(fn ($mediaItem) => array_filter([
                    'source' => $mediaItem->source_url,
                    'thumbnail' => $mediaItem->thumbnail_url ?: $mediaItem->source_url,
                ]))
                ->values()
                ->toArray();
        }

        return [
            'id' => $report->id,
            // reports.created_at 为自定义 DateFormatter cast，经 Carbon 转应用时区格式化
            'time' => \Carbon\Carbon::parse($report->created_at->getTimestamp())
                ->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'reporter' => [
                'id' => $report->reporter->id,
                'username' => $report->reporter->username,
                'url' => url("@{$report->reporter->username}"),
                'ip' => $report->ip_address,
            ],
            'target' => $target,
            'reason' => [
                'title' => $reason['title'] ?? 'Unknown',
                'description' => $reason['description'] ?? null,
            ],
            'comment' => $report->reporter_comment,
            'media' => $media,
            'admin_url' => route('admin.reports.show', ['reportId' => $report->id]),
        ];
    }

    /**
     * 邮件标题固定以「网站举报通知」标识，便于管理员识别与过滤。
     */
    private function buildSubject(Report $report, array $payload): string
    {
        return sprintf(
            '【网站举报通知】#%d %s - %s',
            $report->id,
            $payload['target']['type_label'],
            $payload['reason']['title']
        );
    }
}
