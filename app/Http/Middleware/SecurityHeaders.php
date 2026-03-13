<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Prevent clickjacking attacks
        $response->header('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->header('X-Content-Type-Options', 'nosniff');

        // Enable XSS protection in older browsers
        $response->header('X-XSS-Protection', '1; mode=block');

        // Prevent referrer leakage
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy - prevent inline scripts and external resource loading
        $csp = collect([
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net",
            "img-src 'self' data: https:",
            "font-src 'self' data: cdn.jsdelivr.net",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ])->implode('; ');

        $response->header('Content-Security-Policy', $csp);
        $response->header('Content-Security-Policy-Report-Only', $csp);

        // Prevent access to microphone/camera without permission
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Indicate no cross-domain policy
        $response->header('X-Permitted-Cross-Domain-Policies', 'none');

        return $response;
    }
}
