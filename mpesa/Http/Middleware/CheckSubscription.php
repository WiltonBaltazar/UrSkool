<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string ...$plans  // We can pass plan slugs to the middleware
     */
    public function handle(Request $request, Closure $next, ...$plans): Response
    {
        // Get the authenticated user
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Autenticação necessária para acessar este conteúdo.',
                'details' => null,
            ], 401);
        }

        if (! $user->hasPlan($plans)) {
            return response()->json([
                'code' => 'SUBSCRIPTION_REQUIRED',
                'message' => 'Você não tem um plano válido para acessar este conteúdo.',
                'details' => [
                    'current_plan' => $user->currentPlan()?->slug,
                    'required_plans' => $plans,
                ],
            ], 403);
        }

        return $next($request);
    }
}
