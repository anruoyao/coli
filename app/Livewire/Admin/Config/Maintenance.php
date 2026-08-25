<?php

namespace App\Livewire\Admin\Config;

use App\Events\System\MaintenanceChangedEvent;
use App\Settings\MaintenanceSettings;
use App\Support\Views\Flash;
use Livewire\Component;

/**
 * 全局维护（应急）模式设置页。
 *
 * 开启后：API 统一返回 503 + X-Maintenance（拉兜底），
 * 并广播 MaintenanceChangedEvent（maintenance_on）让所有在线 App 进维护页（推）。
 */
class Maintenance extends Component
{
    public array $formData = [];

    public function mount()
    {
        $settings = app(MaintenanceSettings::class);

        $this->formData = [
            'enabled' => $settings->enabled,
            'message' => $settings->message,
            // settings 存储读回的是字符串（spatie settings 序列化），需 Carbon::parse 再格式化
            'until' => ! empty($settings->until) ? \Illuminate\Support\Carbon::parse($settings->until)->format('Y-m-d H:i') : '',
        ];
    }

    public function submitForm()
    {
        $this->validate([
            'formData.message' => ['nullable', 'string', 'max:500'],
            'formData.until' => ['nullable', 'date'],
        ], attributes: [
            'formData.message' => __('admin/maintenance.form.message'),
            'formData.until' => __('admin/maintenance.form.until'),
        ]);

        $settings = app(MaintenanceSettings::class);

        $enabled = (bool) ($this->formData['enabled'] ?? false);
        $until = ! empty($this->formData['until'])
            ? \Illuminate\Support\Carbon::parse($this->formData['until'])
            : null;

        $settings->enabled = $enabled;
        $settings->message = $this->formData['message'] ?? '';
        $settings->until = $until;
        $settings->save();

        // 广播给所有在线 App（公共命令频道）
        event(new MaintenanceChangedEvent($enabled, $settings->message, $until?->toIso8601String()));

        return redirect()->with('flashMessage', (new Flash(content: $enabled
            ? __('admin/maintenance.flash.enabled')
            : __('admin/maintenance.flash.disabled')))->get())
            ->route('admin.config.maintenance');
    }

    public function render()
    {
        return view('livewire.admin.config.maintenance');
    }
}