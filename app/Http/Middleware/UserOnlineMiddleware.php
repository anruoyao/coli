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

use Closure;
use Illuminate\Http\Request;
use App\Actions\User\UpdateUserDeviceAction;
use Symfony\Component\HttpFoundation\Response;

class UserOnlineMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth_check()) {
            // TODO: Replace with Redis cache storage.
            
            $user = me();

            if ($user->last_active < now()->subMinutes(config('user.online_interval_in_minutes'))) {
                $user->last_active = now();
                $user->save();

                (new UpdateUserDeviceAction())->execute($user);
            }
        }

        return $next($request);
    }
}
