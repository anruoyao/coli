<?php

namespace App\Http\Controllers;

use App\Services\Seo\SeoResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * 前端 SPA shell / 公开 SEO 页统一处理器。
 *
 * 行为：
 * - 已登录用户：与之前 catch-all 一致，渲染桌面/mobile SPA shell；
 * - 未登录访客命中可公开收录路径（用户主页/帖子/故事/职位/商品）：
 *   渲染服务端 SEO HTML（meta + JSON-LD + 正文快照），解决 SPA 空壳抓取问题；
 * - 未登录访客命中其余路径：保持原有行为，重定向登录页。
 */
class SeoController extends Controller
{
    public function __invoke(Request $request): \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
    {
        if (auth_check()) {
            return $this->shell();
        }

        $seo = app(SeoResolver::class)->resolve($request->path());

        if ($seo) {
            return view('apps.seo.index', [
                'seo' => $seo,
            ]);
        }

        return redirect()->guest(route('user.auth.index'));
    }

    protected function shell(): \Illuminate\Contracts\View\View
    {
        $deviceType = Cookie::get('device_type', 'desktop');

        return view($deviceType === 'mobile' ? 'mobile::index' : 'desktop::index');
    }
}