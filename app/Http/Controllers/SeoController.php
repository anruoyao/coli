<?php

namespace App\Http\Controllers;

use App\Services\Seo\SeoResolver;
use App\Settings\GuestSettings;
use App\Support\Guest\CrawlerDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Jenssegers\Agent\Agent;

/**
 * 前端 SPA shell / 公开 SEO 页统一处理器。
 *
 * 行为：
 * - 已登录用户：渲染桌面/mobile SPA shell（保持原行为，user.status 处理封禁/引导）；
 * - 未登录爬虫/分享抓取器（任意状态）：始终服务端输出 SEO HTML（meta + JSON-LD + 快照），
 *   保证收录与社交平台链接卡片；
 * - 未登录真人 + 访客功能开启：渲染 SPA shell（访客模式，内容走 /api/guest/v1）；
 * - 未登录真人命中可公开收录路径：渲染服务端 SEO HTML（访客功能关闭时的行为）；
 * - 其余情况：重定向登录页（保持原行为）。
 */
class SeoController
{
    public function __invoke(Request $request): \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
    {
        if (auth_check()) {
            return $this->shell();
        }

        // 爬虫/分享抓取器：始终 SEO HTML，不受访客开关影响。
        if (CrawlerDetector::isCrawler($request->userAgent())) {
            return $this->seo($request);
        }

        // 真人 + 访客开关开启：SPA shell（访客模式）。
        if (app(GuestSettings::class)->enabled) {
            return $this->shell();
        }

        return $this->seoOrLogin($request);
    }

    protected function seo(Request $request): \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
    {
        $seo = app(SeoResolver::class)->resolve($request->path());

        if ($seo) {
            return view('apps.seo.index', [
                'seo' => $seo,
            ]);
        }

        return redirect()->guest(route('user.auth.index'));
    }

    protected function seoOrLogin(Request $request): \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
    {
        return $this->seo($request);
    }

    protected function shell(): \Illuminate\Contracts\View\View
    {
        $deviceType = Cookie::get('device_type', 'desktop');

        // 当前 shell 与设备类型一致时不展示切换提示（否者反复横跳）；
        // 不一致时展示"切换到另一端"浮层，点击切换后自动消除。
        $isMobileUa = $this->isMobileUserAgent();

        $showSwitcher = ($deviceType === 'desktop' && $isMobileUa)
            || ($deviceType === 'mobile' && ! $isMobileUa);

        return view($deviceType === 'mobile' ? 'mobile::index' : 'desktop::index', [
            'showDeviceSwitcher' => $showSwitcher,
        ]);
    }

    /**
     * UA 是否为手机/平板（iPadOS 13+ 桌面 UA 伪装需单独识别）。
     */
    protected function isMobileUserAgent(): bool
    {
        $userAgent = (string) request()->userAgent();

        if ($userAgent === '') {
            return false;
        }

        $agent = new Agent();
        $agent->setUserAgent($userAgent);

        // iPadOS 13+ 桌面 UA 伪装为 Macintosh，唯一区别是带 Mobile token。
        $isIpadOs = preg_match('/Macintosh/i', $userAgent) && preg_match('/Mobile/i', $userAgent);

        return $agent->isMobile() || $agent->isTablet() || $isIpadOs;
    }
}