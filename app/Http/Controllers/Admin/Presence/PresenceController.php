<?php

namespace App\Http\Controllers\Admin\Presence;

use App\Http\Controllers\Controller;

/**
 * 后台「在线用户」（P0 在线用户数）。
 *
 * 页面主体为 Livewire 组件 admin.presence.online-list（分平台统计/筛选/搜索/poll 自动刷新）。
 */
class PresenceController extends Controller
{
    public function index()
    {
        return view('admin::presence.index.index');
    }
}