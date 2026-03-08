<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $contentType = null): Response
    {
        $user = $request->user();

        //Podcasts are always free - no auth required
        if($contentType === 'podcast') {
            return $next($request);
        }

        if(!$user){
            return response()->json([
                'error' => 'Auhentication required for premium content',
                'login_required' => true
            ], 401);
        }

        if(!$user->hasActivePlan($contentType)) {
            return response()->json([
                'error' => 'You do not have an active subscription for this content',
                'subscription_required' => true
            ], 403);
        }

        $access = $user->getContentAccess();

        if($contentType && !$access[$contentType]){
            return response()->json([
                'error' => "Access denied for {$contentType}",
                'current_plan' => $user->currentPlan()?->slug,
                'required_access' => $contentType,
                'upgrade_required' => true
            ], 403);
        }

        return $next($request);
    }
}
