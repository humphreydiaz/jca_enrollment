<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);
        $request->headers->set('X-Request-ID', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set(
            'X-Frame-Options',
            $request->routeIs('requirements.documents.preview') ? 'SAMEORIGIN' : 'DENY',
        );
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->remove('X-Powered-By');

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        if (app()->environment('production') && $request->isSecure() && config('security.hsts.enabled')) {
            $value = 'max-age='.(int) config('security.hsts.max_age');
            if (config('security.hsts.include_subdomains')) {
                $value .= '; includeSubDomains';
            }
            $response->headers->set('Strict-Transport-Security', $value);
        }

        return $response;
    }

    private function requestId(Request $request): string
    {
        $provided = (string) $request->header('X-Request-ID');

        return preg_match('/\A[A-Za-z0-9_-]{8,64}\z/', $provided) === 1
            ? $provided
            : (string) Str::ulid();
    }
}
