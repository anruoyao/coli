<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuestOrAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth_check()) {
            return app(UserStatusMiddleware::class)->handle($request, $next);
        }

        return $next($request);
    }
}
