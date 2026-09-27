<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 营销活动邮件。
 * 视图复用平台邮件品牌样式（emails.layouts.main + x-emails.* 组件）。
 * 批量发送时由 SendMarketingEmailJob 逐封发送（QQ SMTP 智能限速）。
 */
class MarketingNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectText,
        public string $campaignTitle,
        public string $campaignContent,
        public ?string $destinationUrl = null,
        public string $locale = 'en',
        public string $appName = '',
    ) {
        $this->appName = $appName !== '' ? $appName : (string) config('app.name');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectText);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user.notifications.marketing',
            with: [
                'campaignTitle' => $this->campaignTitle,
                'campaignContent' => $this->campaignContent,
                'destinationUrl' => $this->destinationUrl,
                'locale' => $this->locale,
                'appName' => $this->appName,
            ],
        );
    }
}