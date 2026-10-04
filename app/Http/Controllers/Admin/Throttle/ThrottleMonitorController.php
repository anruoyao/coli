<?php

namespace App\Http\Controllers\Admin\Throttle;

use App\Http\Controllers\Controller;

/**
 * 后台「API 限流监控」（只读）。
 *
 * 展示 429 限流命中事件（框架 throttle / AbuseGuard 风控 / 全局 IP 闸门三道防线），
 * 数据来源 api_throttle_events 表，主体为 Livewire 组件 admin.throttle.throttle-monitor。
 */
class ThrottleMonitorController extends Controller
{
    public function index()
    {
        return view('admin::throttle.monitor.index');
    }
}
