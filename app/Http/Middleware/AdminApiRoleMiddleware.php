<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminApiRoleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if (! $user || ! ($user->isAdmin() || $user->isRoot())) {
            return response()->json([
                'status' => 'error',
                'code' => 403,
                'message' => 'Administrator access is required.',
            ], 403);
        }

        return $next($request);
    }
}
