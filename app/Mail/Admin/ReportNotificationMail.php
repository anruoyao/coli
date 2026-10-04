<?php

namespace App\Mail\Admin;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 举报通知邮件（发往管理员邮箱）。
 *
 * 由 SendReportNotificationEmailJob 同步发送（Job 本身在 database 队列异步执行），
 * 载荷由 Job 预构建，包含举报时间/举报人/被举报内容/理由/补充说明/证据媒体。
 * 所有文本在 Blade 中经 {{ }} 转义输出（XSS 防护）。
 */
class ReportNotificationMail extends Mailable
{
    public function __construct(
        public string $subject,
        public array $payload
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.notifications.report-notification',
            with: [
                'report' => $this->payload
            ]
        );
    }
}
