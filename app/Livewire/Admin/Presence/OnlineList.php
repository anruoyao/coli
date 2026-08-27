<?php

namespace App\Livewire\Admin\Presence;

use App\Models\PresenceSession;
use App\Services\User\PresenceService;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 后台「在线用户」列表（P0 在线用户数）。
 *
 * 实时性：wire:poll.30s 自动刷新（体现「在线」的时效性），另提供手动刷新按钮；
 * 筛选：平台 Tab（全部/Web/Android/iOS）+ 用户关键字搜索；分页 + 每页自动重置。
 */
class OnlineList extends Component
{
    use WithPagination;

    public string $platform = 'all';

    public string $search = '';

    protected $queryString = [
        'platform' => ['except' => 'all'],
        'search'   => ['except' => ''],
    ];

    public function mount()
    {
        $query = request()->string('platform')->value;
        $this->platform = in_array($query, ['all', 'web', 'android', 'ios']) ? $query : 'all';
        $this->search   = (string) request()->string('search')->value;
    }

    public function applyPlatform(string $platform)
    {
        $this->platform = in_array($platform, ['all', 'web', 'android', 'ios']) ? $platform : 'all';
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $counts = app(PresenceService::class)->onlineCounts();

        $sessions = PresenceSession::query()
            ->with('user:id,first_name,last_name,username,avatar,caption,email')
            ->online()
            ->when($this->platform !== 'all', fn ($query) => $query->where('platform', $this->platform))
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);
                $query->whereHas('user', fn ($user) => $user
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"));
            })
            ->orderByDesc('last_seen_at')
            ->paginate(15);

        return view('livewire.admin.presence.online-list', [
            'counts'   => $counts,
            'sessions' => $sessions,
        ]);
    }
}