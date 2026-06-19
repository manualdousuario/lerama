<?php

declare(strict_types=1);

namespace Lerama\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $response = $response->withHeader('X-Content-Type-Options', 'nosniff');
        $response = $response->withHeader('X-Frame-Options', 'DENY');
        $response = $response->withHeader('X-XSS-Protection', '1; mode=block');
        $response = $response->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response = $response->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $frameSrc = $request->getUri()->getPath() === '/shuffle'
            ? "frame-src 'self' https: http:; "
            : "frame-src 'self'; ";

        $csp = "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline'; "
             . "style-src 'self' 'unsafe-inline'; "
             . "img-src 'self' data: https: http:; "
             . "font-src 'self'; "
             . "connect-src 'self'; "
             . $frameSrc
             . "frame-ancestors 'none'; "
             . "base-uri 'self'; "
             . "form-action 'self';";
        $response = $response->withHeader('Content-Security-Policy', $csp);

        $uri = $request->getUri();
        if ($uri->getScheme() === 'https') {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
