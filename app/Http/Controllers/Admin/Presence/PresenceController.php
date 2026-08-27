<?php

namespace App\Http\Controllers\Admin\Presence;

use App\Models\PresenceSession;
use App\Services\User\PresenceService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * 后台「在线用户」（P0 实时列表 + P1 数据分析/导出）。
 *
 * - 实时列表：views=live（默认），主体为 Livewire 组件 admin.presence.online-list
 * - 数据分析：views=analytics，渲染 24h/7d 趋势 + DAU/WAU/MAU + 今日峰值（ApexCharts）
 * - 导出：GET /admin/presence/export 生成当前在线会话 CSV（UTF-8 BOM，Excel 友好）
 */
class PresenceController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->string('view')->value === 'analytics' ? 'analytics' : 'live';

        $stats = null;
        if ($view === 'analytics') {
            $stats = app(PresenceService::class)->activityStats();
        }

        return view('admin::presence.index.index', [
            'view'  => $view,
            'stats' => $stats,
        ]);
    }

    public function export(Request $request)
    {
        $platform = $request->string('platform')->value;
        $platform = in_array($platform, ['web', 'android', 'ios']) ? $platform : null;
        $search   = trim((string) $request->string('search')->value);

        $sessions = PresenceSession::query()
            ->with('user:id,first_name,last_name,username,email')
            ->online()
            ->when($platform, fn ($query) => $query->where('platform', $platform))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('user', fn ($user) => $user
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderByDesc('last_seen_at')
            ->get();

        $handle = fopen('php://temp', 'r+');

        // UTF-8 BOM：保证 Excel 直接打开中文不乱码
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            __('admin/presence.export.headers.user'),
            __('table.labels.email'),
            __('admin/presence.export.headers.platform'),
            __('admin/presence.export.headers.platform_detail'),
            __('admin/presence.export.headers.ip'),
            __('admin/presence.export.headers.location'),
            __('admin/presence.table.online_since'),
            __('admin/presence.table.last_seen'),
        ]);

        foreach ($sessions as $session) {
            fputcsv($handle, [
                $session->user?->name ? trim($session->user->name) : '#' . $session->user_id,
                $session->user?->email,
                PresenceSession::PLATFORM[$session->platform] ?? $session->platform,
                $session->platform_detail,
                $session->ip_address,
                trim(($session->city ?? '') . ' ' . ($session->country ?? '')),
                $session->started_at?->format('Y-m-d H:i:s'),
                $session->last_seen_at?->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $filename = 'online-users-' . now()->format('Ymd-His') . ($platform ? '-' . $platform : '') . '.csv';

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }
}