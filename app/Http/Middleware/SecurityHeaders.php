<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Note the frame-src exception for /shuffle, which iframes external sites.
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $frameSrc = $request->is('shuffle') ? "'self' https: http:" : "'self'";

        $response->headers->set(
            'Content-Security-Policy',
            // 'unsafe-eval' is required by Alpine.js (bundled with Livewire) to
            // evaluate expressions like x-on:click through `new Function()`.
            "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; "
            ."img-src 'self' data: https: http:; font-src 'self'; connect-src 'self'; "
            ."frame-src {$frameSrc}; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
        );

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
