<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Webhooks are verified using signature instead
        'webhooks/ses',
        'webhooks/sns',
        'webhooks/freeswitch',
        
        // Health check endpoint
        'health',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // For API requests with JWT, we don't enforce CSRF token
        // CSRF is only for session-based authentication
        if ($request->header('Authorization')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
