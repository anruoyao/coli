<?php

namespace App\Livewire\Admin\Presence;

use App\Models\PresenceSession;
use App\Models\User;
use App\Services\User\PresenceService;
use Illuminate\Support\Facades\Redis;
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

    /**
     * 在线踢下线（P1）：从在线列表移除会话（DB + Redis）+ 删除该用户全部 Sanctum token（App 强制下线）。
     * 网页会话保留（Cookie），但用户不再计入在线；App 下一请求即 401 走全局登出。
     */
    public function kickout(int $sessionId)
    {
        $session = PresenceSession::query()->findOrFail($sessionId);

        $userId   = $session->user_id;
        $platform = $session->platform;
        $member   = $userId . ':' . $session->client_id;

        try {
            Redis::zrem(PresenceService::redisKey($platform), $member);
        } catch (\Throwable $e) {
            // Redis 异常不阻断踢下线（DB 删除后自然不再计入在线）
        }

        $session->delete();

        User::query()->whereKey($userId)->first()?->tokens()?->delete();

        $this->dispatch('presence-user-kicked', userId: $userId);
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