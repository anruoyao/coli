<?php

namespace App\Livewire\Admin\Config;

use App\Models\AppVersion;
use App\Support\Views\Flash;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * App 版本管理（规格列表 + 创建/编辑 + 启停/删除）。
 *
 * 列表页：展示全部版本，支持编辑、删除、上线/下线、切换强制更新；
 * 表单页：新建 / 编辑版本（版本代号、平台、下载链接、更新公告、发布时间、强制/上线开关）。
 */
class Versions extends Component
{
    public string $mode = 'list';

    public array $formData = [];

    public ?int $editingId = null;

    protected function rules(): array
    {
        return [
            'formData.code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[0-9]+(\.[0-9]+){1,3}$/',
                Rule::unique('app_versions', 'code')
                    ->where(fn ($query) => $query->where('platform', $this->formData['platform'] ?? 'android'))
                    ->ignore($this->editingId),
            ],
            'formData.platform' => ['required', 'in:android,ios'],
            'formData.download_url' => ['required', 'url', 'max:500'],
            'formData.notes' => ['nullable', 'string', 'max:5000'],
            'formData.is_forced' => ['boolean'],
            'formData.is_active' => ['boolean'],
            'formData.released_at' => ['nullable', 'date'],
        ];
    }

    public function openCreate()
    {
        $this->mode = 'form';
        $this->editingId = null;
        $this->formData = [
            'code' => '',
            'platform' => 'android',
            'download_url' => '',
            'notes' => '',
            'is_forced' => false,
            'is_active' => true,
            'released_at' => '',
        ];
    }

    public function openEdit(int $id)
    {
        $version = AppVersion::query()->findOrFail($id);

        $this->mode = 'form';
        $this->editingId = $version->id;
        $this->formData = [
            'code' => $version->code,
            'platform' => $version->platform,
            'download_url' => $version->download_url,
            'notes' => $version->notes ?? '',
            'is_forced' => $version->is_forced,
            'is_active' => $version->is_active,
            'released_at' => $version->released_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    public function backToList()
    {
        $this->mode = 'list';
        $this->editingId = null;
        $this->formData = [];
    }

    public function save()
    {
        $validated = $this->validate();

        $data = [
            'code' => $validated['formData']['code'],
            'platform' => $validated['formData']['platform'],
            'download_url' => $validated['formData']['download_url'],
            'notes' => $validated['formData']['notes'] ?: null,
            'is_forced' => (bool) ($validated['formData']['is_forced'] ?? false),
            'is_active' => (bool) ($validated['formData']['is_active'] ?? true),
            'released_at' => ! empty($validated['formData']['released_at'])
                ? \Illuminate\Support\Carbon::parse($validated['formData']['released_at'])
                : null,
        ];

        if ($this->editingId) {
            AppVersion::query()->findOrFail($this->editingId)->update($data);
            $message = __('admin/version.flash.updated');
        } else {
            AppVersion::query()->create($data);
            $message = __('admin/version.flash.created');
        }

        return redirect()->with('flashMessage', (new Flash(content: $message))->get())
            ->route('admin.config.versions');
    }

    public function toggleForced(int $id)
    {
        $version = AppVersion::query()->findOrFail($id);
        $version->update(['is_forced' => ! $version->is_forced]);
    }

    public function toggleActive(int $id)
    {
        $version = AppVersion::query()->findOrFail($id);
        $version->update(['is_active' => ! $version->is_active]);
    }

    public function delete(int $id)
    {
        AppVersion::query()->findOrFail($id)->delete();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/version.flash.deleted')))->get())
            ->route('admin.config.versions');
    }

    public function render()
    {
        $versions = AppVersion::query()
            ->orderBy('platform')
            ->orderByDesc(\Illuminate\Support\Facades\DB::raw('COALESCE(released_at, created_at)'))
            ->get();

        return view('livewire.admin.config.versions', [
            'versions' => $versions,
        ]);
    }
}