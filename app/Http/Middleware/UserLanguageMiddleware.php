<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Middleware;

use App\Support\Languages;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class UserLanguageMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $fallback = Languages::normalizeCode(config('app.locale')) ?: 'zh';

        if (auth_check()) {
            return Languages::normalizeCode(me()->language) ?: $fallback;
        }

        $languages = new Languages();

        // 游客请求（注册、验证码、找回密码）没有账户可依据：依次使用显式
        // language 参数、已选择的语言 Cookie/Session，最后回退到设备语言头。
        $candidates = [
            $request->input('language'),
            Cookie::get('selected_locale'),
            session()->get('selected_locale'),
        ];

        foreach (explode(',', (string) $request->header('Accept-Language', '')) as $part) {
            $candidates[] = explode(';', $part)[0];
        }

        foreach ($candidates as $candidate) {
            $code = Languages::normalizeCode($candidate);

            if ($code !== '' && $languages->languageExists($code)) {
                return $code;
            }
        }

        return $fallback;
    }
}
