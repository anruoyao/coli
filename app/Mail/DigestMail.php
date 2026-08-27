<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 聚合 Digest 摘要邮件。
 * 视图复用 emails.user.notifications.digest（全局 composer 会注入 logotypeUrl 等）。
 */
class DigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectText,
        public array $digestData,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectText);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user.notifications.digest',
            with: $this->digestData,
        );
    }
}