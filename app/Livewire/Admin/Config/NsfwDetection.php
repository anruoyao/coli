<?php

namespace App\Livewire\Admin\Config;

use App\Models\AdminConfigChangeLog;
use App\Settings\NsfwDetectionSettings;
use App\Services\Nsfw\NsfwDetectionService;
use Livewire\Component;
use Throwable;

/**
 * NSFW 自动识别配置（后台「通知」分组）。
 *
 * - 功能总开关 / 置信度阈值 / 触发标签集 / 作者通知开关
 * - 检测服务连通性测试
 * - 所有变更写入 admin_config_change_logs 审计
 */
class NsfwDetection extends Component
{
    public bool $featureEnabled = false;

    public string $threshold = '0.60';

    public string $triggerLabels = '';

    public bool $notifyAuthor = true;

    public ?string $flashContent = null;

    public string $flashType = 'success';

    public function mount(): void
    {
        $settings = app(NsfwDetectionSettings::class);

        $this->featureEnabled = $settings->enabled;
        $this->threshold = (string) $settings->threshold;
        $this->triggerLabels = implode("\n", $settings->trigger_labels);
        $this->notifyAuthor = $settings->notify_author;
    }

    public function saveSettings(): void
    {
        $this->resetValidation();

        $this->validate([
            'threshold' => ['required', 'numeric', 'min:0.1', 'max:0.99'],
        ], attributes: [
            'threshold' => __('admin/config.form.nsfw_threshold'),
        ]);

        $settings = app(NsfwDetectionSettings::class);

        $old = [
            'enabled' => $settings->enabled,
            'threshold' => $settings->threshold,
            'trigger_labels' => $settings->trigger_labels,
            'notify_author' => $settings->notify_author,
        ];

        $labels = $this->parseTriggerLabels();

        if (empty($labels)) {
            $this->addError('triggerLabels', __('admin/config.form.nsfw_labels_required'));

            return;
        }

        $settings->enabled = $this->featureEnabled;
        $settings->threshold = (float) $this->threshold;
        $settings->trigger_labels = $labels;
        $settings->notify_author = $this->notifyAuthor;
        $settings->save();

        AdminConfigChangeLog::record('nsfw_detection', 'settings_updated', [
            'from' => $old,
            'to' => [
                'enabled' => $settings->enabled,
                'threshold' => $settings->threshold,
                'trigger_labels' => $settings->trigger_labels,
                'notify_author' => $settings->notify_author,
            ],
        ]);

        $this->flash(__('admin/flash.config.settings_success'), 'success');
    }

    /**
     * 检测服务连通性测试（GET /health）。
     */
    public function testConnection(): void
    {
        try {
            $result = app(NsfwDetectionService::class)->healthCheck();

            if (! empty($result['ok'])) {
                $this->flash(__('admin/config.flash.nsfw_test_ok', ['model' => $result['model'] ?? 'unknown']), 'success');
            }
            else {
                $this->flash(__('admin/config.flash.nsfw_test_unhealthy'), 'error');
            }
        } catch (Throwable $e) {
            $this->flash(__('admin/config.flash.nsfw_test_failed', ['error' => $e->getMessage()]), 'error');
        }
    }

    public function render()
    {
        return view('livewire.admin.config.nsfw-detection', [
            'changeLogs' => AdminConfigChangeLog::where('config_key', 'nsfw_detection')
                ->with('admin')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * 文本域（换行/逗号分隔）解析为去重大写标签数组
     */
    private function parseTriggerLabels(): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                fn (string $label) => strtoupper(trim($label)),
                preg_split('/[\r\n,]+/', $this->triggerLabels) ?: []
            )
        )));
    }

    private function flash(string $content, string $type = 'success'): void
    {
        $this->flashContent = $content;
        $this->flashType = $type;
    }
}
