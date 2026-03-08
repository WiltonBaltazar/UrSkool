<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CookieAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // If there's a token in the cookie but not in the Authorization header,
        // add it to the header for Sanctum to process
        if ($request->hasCookie('auth_token') && !$request->bearerToken()) {
            $token = $request->cookie('auth_token');
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}