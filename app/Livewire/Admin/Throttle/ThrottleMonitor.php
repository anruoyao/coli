<?php

namespace App\Livewire\Admin\Throttle;

use App\Models\ApiThrottleEvent;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 后台「API 限流监控」列表（只读）。
 *
 * 数据：api_throttle_events（429 命中审计，三道防线：throttle:api.* / abuse:* / global-ip-gate）。
 * 筛选：时间范围（今日/24h/7d）+ 维度 Tab（全部/用户/IP/设备/全局）+ 分类下拉 + 标识符搜索；
 * 顶部统计卡片：今日 429 总数、用户维度、IP 维度、全局闸门命中。
 */
class ThrottleMonitor extends Component
{
    use WithPagination;

    public string $dimension = 'all';

    public string $range = 'today';

    public string $category = '';

    public string $search = '';

    protected $queryString = [
        'dimension' => ['except' => 'all'],
        'range'     => ['except' => 'today'],
        'category'  => ['except' => ''],
        'search'    => ['except' => ''],
    ];

    public function mount()
    {
        $this->dimension = in_array(request()->string('dimension')->value, ['all', 'user', 'ip', 'device', 'global'], true)
            ? request()->string('dimension')->value : 'all';
        $this->range = in_array(request()->string('range')->value, ['today', '24h', '7d'], true)
            ? request()->string('range')->value : 'today';
        $this->category = (string) request()->string('category')->value;
        $this->search = (string) request()->string('search')->value;
    }

    public function applyDimension(string $dimension)
    {
        $this->dimension = in_array($dimension, ['all', 'user', 'ip', 'device', 'global'], true) ? $dimension : 'all';
        $this->resetPage();
    }

    public function applyRange(string $range)
    {
        $this->range = in_array($range, ['today', '24h', '7d'], true) ? $range : 'today';
        $this->resetPage();
    }

    public function updatedCategory()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $todayStart = now()->startOfDay();

        $stats = [
            'total'  => ApiThrottleEvent::where('created_at', '>=', $todayStart)->count(),
            'user'   => ApiThrottleEvent::where('created_at', '>=', $todayStart)->where('dimension', ApiThrottleEvent::DIMENSION_USER)->count(),
            'ip'     => ApiThrottleEvent::where('created_at', '>=', $todayStart)->where('dimension', ApiThrottleEvent::DIMENSION_IP)->count(),
            'gate'   => ApiThrottleEvent::where('created_at', '>=', $todayStart)->where('category', 'global-ip-gate')->count(),
        ];

        $events = ApiThrottleEvent::query()
            ->when($this->dimension !== 'all', fn ($query) => $query->where('dimension', $this->dimension))
            ->when($this->category !== '', fn ($query) => $query->where('category', $this->category))
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);
                $query->where(fn ($q) => $q
                    ->where('identifier', 'like', "%{$term}%")
                    ->orWhere('path', 'like', "%{$term}%")
                    ->orWhere('ip_address', 'like', "%{$term}%"));
            })
            ->when($this->range === 'today', fn ($query) => $query->where('created_at', '>=', $todayStart))
            ->when($this->range === '24h', fn ($query) => $query->where('created_at', '>=', now()->subDay()))
            ->when($this->range === '7d', fn ($query) => $query->where('created_at', '>=', now()->subDays(7)))
            ->orderByDesc('id')
            ->paginate(20);

        $categories = ApiThrottleEvent::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values();

        return view('livewire.admin.throttle.throttle-monitor', [
            'stats'      => $stats,
            'events'     => $events,
            'categories' => $categories,
        ]);
    }
}
