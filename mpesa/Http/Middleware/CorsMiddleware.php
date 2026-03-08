<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $origin = (string) $request->headers->get('Origin', '');
        $allowedOrigins = array_values(array_filter(
            (array) config('cors.allowed_origins', []),
            static fn (mixed $value): bool => is_string($value) && trim($value) !== ''
        ));
        $allowOrigin = in_array($origin, $allowedOrigins, true) ? $origin : null;

        $allowedMethods = config('cors.allowed_methods', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
        if (! is_array($allowedMethods) || $allowedMethods === []) {
            $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        }

        $allowedHeaders = config('cors.allowed_headers', ['Content-Type', 'Authorization']);
        if (! is_array($allowedHeaders) || $allowedHeaders === []) {
            $allowedHeaders = ['Content-Type', 'Authorization'];
        }

        if ($request->isMethod('OPTIONS')) {
            $response = response()->noContent(204);

            if ($allowOrigin !== null) {
                $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
                $response->headers->set('Vary', 'Origin');
                $response->headers->set('Access-Control-Allow-Credentials', config('cors.supports_credentials', false) ? 'true' : 'false');
            }

            $response->headers->set('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
            $response->headers->set('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));

            return $response;
        }

        $response = $next($request);

        if ($allowOrigin !== null) {
            $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
            $response->headers->set('Vary', 'Origin');
            $response->headers->set('Access-Control-Allow-Credentials', config('cors.supports_credentials', false) ? 'true' : 'false');
        }

        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));

        return $response;
    }
}
