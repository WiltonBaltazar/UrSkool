<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFilamentAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'canAccessPanel')) {
            abort(403);
        }

        $panel = Filament::getCurrentPanel();

        if (! $panel || ! $user->canAccessPanel($panel)) {
            abort(403);
        }

        return $next($request);
    }
}
