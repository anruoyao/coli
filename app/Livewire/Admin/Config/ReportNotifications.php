<?php

namespace App\Livewire\Admin\Config;

use App\Mail\Admin\ReportNotificationMail;
use App\Models\AdminConfigChangeLog;
use App\Models\ReportNotificationEmail;
use App\Settings\ReportNotificationSettings;
use Livewire\Component;
use Throwable;
use Illuminate\Support\Facades\Mail;

/**
 * 举报通知配置（后台「通知设置」分组）。
 *
 * - 管理员邮箱 CRUD（encrypted 存储，支持多邮箱）
 * - 每邮箱独立开关 / 编辑 / 删除 / 发送测试邮件
 * - 通知总开关
 * - 所有变更写入 admin_config_change_logs 审计
 */
class ReportNotifications extends Component
{
    public const MAX_EMAILS = 20;

    public bool $featureEnabled = true;

    public string $newEmail = '';

    public ?int $editingId = null;

    public string $editingEmail = '';

    public ?string $flashContent = null;

    public string $flashType = 'success';

    public function mount(): void
    {
        $this->featureEnabled = app(ReportNotificationSettings::class)->enabled;
    }

    /**
     * 通知总开关。
     */
    public function toggleFeature(): void
    {
        $this->validate([
            'featureEnabled' => ['required', 'boolean'],
        ]);

        $settings = app(ReportNotificationSettings::class);

        $old = $settings->enabled;

        $settings->enabled = $this->featureEnabled;
        $settings->save();

        AdminConfigChangeLog::record('report_notification', 'feature_toggled', [
            'from' => $old,
            'to' => $this->featureEnabled,
        ]);

        $this->flash(__('admin/flash.config.settings_success'), 'success');
    }

    /**
     * 新增管理员邮箱（校验格式 / 去重 / 上限）。
     */
    public function addEmail(): void
    {
        $this->resetValidation();

        $this->validate([
            'newEmail' => [
                'required',
                'email:rfc',
                'max:255',
                function (string $attribute, mixed $value, callable $fail) {
                    if(! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return;
                    }

                    if(ReportNotificationEmail::count() >= self::MAX_EMAILS) {
                        $fail(__('admin/config.form.report_emails_limit', ['limit' => self::MAX_EMAILS]));

                        return;
                    }

                    if($this->emailExists((string) $value)) {
                        $fail(__('admin/config.form.report_email_duplicate'));
                    }
                },
            ],
        ], attributes: [
            'newEmail' => __('admin/config.form.report_admin_email'),
        ]);

        $email = ReportNotificationEmail::create([
            'email' => mb_strtolower(trim($this->newEmail)),
            'enabled' => true,
        ]);

        AdminConfigChangeLog::record('report_notification', 'email_created', [
            'email' => $email->email,
        ]);

        $this->newEmail = '';

        $this->flash(__('admin/config.flash.report_email_added', ['email' => $email->email]), 'success');
    }

    /**
     * 进入行内编辑。
     */
    public function startEditing(int $id): void
    {
        $email = ReportNotificationEmail::find($id);

        if($email) {
            $this->editingId = $id;
            $this->editingEmail = $email->email;
        }
    }

    public function cancelEditing(): void
    {
        $this->editingId = null;
        $this->editingEmail = '';
        $this->resetValidation();
    }

    /**
     * 保存编辑。
     */
    public function updateEmail(): void
    {
        $target = ReportNotificationEmail::find($this->editingId);

        if(! $target) {
            $this->cancelEditing();

            return;
        }

        $this->resetValidation();

        $this->validate([
            'editingEmail' => [
                'required',
                'email:rfc',
                'max:255',
                function (string $attribute, mixed $value, callable $fail) use ($target) {
                    if(! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return;
                    }

                    if($this->emailExists((string) $value, $target->id)) {
                        $fail(__('admin/config.form.report_email_duplicate'));
                    }
                },
            ],
        ], attributes: [
            'editingEmail' => __('admin/config.form.report_admin_email'),
        ]);

        $old = $target->email;

        $target->update([
            'email' => mb_strtolower(trim($this->editingEmail)),
        ]);

        AdminConfigChangeLog::record('report_notification', 'email_updated', [
            'from' => $old,
            'to' => $target->fresh()->email,
        ]);

        $this->cancelEditing();

        $this->flash(__('admin/config.flash.report_email_updated'), 'success');
    }

    /**
     * 单邮箱启用/停用。
     */
    public function toggleEmail(int $id): void
    {
        $target = ReportNotificationEmail::find($id);

        if(! $target) {
            return;
        }

        $old = $target->enabled;

        $target->update([
            'enabled' => ! $old,
        ]);

        AdminConfigChangeLog::record('report_notification', 'email_toggled', [
            'email' => $target->email,
            'from' => $old,
            'to' => ! $old,
        ]);
    }

    /**
     * 删除邮箱。
     */
    public function removeEmail(int $id): void
    {
        $target = ReportNotificationEmail::find($id);

        if(! $target) {
            return;
        }

        $email = $target->email;

        $target->delete();

        AdminConfigChangeLog::record('report_notification', 'email_deleted', [
            'email' => $email,
        ]);

        $this->flash(__('admin/config.flash.report_email_deleted', ['email' => $email]), 'success');
    }

    /**
     * 向指定邮箱发送测试邮件（复用举报通知邮件模板）。
     */
    public function sendTestEmail(int $id): void
    {
        $target = ReportNotificationEmail::find($id);

        if(! $target) {
            return;
        }

        $payload = [
            'id' => 0,
            'time' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'reporter' => [
                'id' => 0,
                'username' => 'test_user',
                'url' => url('/'),
                'ip' => null,
            ],
            'target' => [
                'id' => 0,
                'type_label' => __('admin/notifications.report.title'),
                'url' => url('/'),
                'preview' => null,
            ],
            'reason' => [
                'title' => __('admin/config.form.report_test_email_reason'),
                'description' => null,
            ],
            'comment' => __('admin/config.form.report_test_email_body', ['app_name' => config('app.name')]),
            'media' => [],
            'admin_url' => route('admin.config.report-notifications'),
        ];

        try {
            Mail::to($target->email)->send(
                new ReportNotificationMail(__('admin/config.form.report_test_email_subject'), $payload)
            );

            AdminConfigChangeLog::record('report_notification', 'test_email_sent', [
                'email' => $target->email,
            ]);

            $this->flash(__('admin/config.flash.report_test_email_sent', ['email' => $target->email]), 'success');
        } catch (Throwable $e) {
            $this->flash(__('admin/config.flash.report_test_email_failed', ['error' => $e->getMessage()]), 'error');
        }
    }

    public function render()
    {
        return view('livewire.admin.config.report-notifications', [
            'emails' => ReportNotificationEmail::orderBy('id')->get(),
            'changeLogs' => AdminConfigChangeLog::where('config_key', 'report_notification')
                ->with('admin')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'rateLimit' => [
                'max' => (int) config('security.reports.max_per_day', 10),
                'window' => (int) config('security.reports.window_hours', 24),
            ],
        ]);
    }

    private function emailExists(string $value, ?int $ignoreId = null): bool
    {
        $normalized = mb_strtolower(trim($value));

        return ReportNotificationEmail::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->contains(fn ($item) => $item->email === $normalized);
    }

    private function flash(string $content, string $type = 'success'): void
    {
        $this->flashContent = $content;
        $this->flashType = $type;
    }
}
