<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CorsMiddleware
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
        $allowedOrigins = $this->getAllowedOrigins();
        $origin = $request->header('Origin');

        // Check if origin is allowed
        $isOriginAllowed = in_array($origin, $allowedOrigins, true) ||
                          $this->isOriginAllowedByPattern($origin, $allowedOrigins);

        if ($isOriginAllowed || in_array('*', $allowedOrigins, true)) {
            /** @var Response $response */
            $response = $next($request);

            $response->header('Access-Control-Allow-Origin', $origin ?? '*');
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Max-Age', '86400');

            return $response;
        }

        // If preflight request, return 200
        if ($request->isMethod('OPTIONS')) {
            return response()->noContent(200);
        }

        return $next($request);
    }

    /**
     * Get allowed origins from environment configuration.
     *
     * @return array<int, string>
     */
    private function getAllowedOrigins(): array
    {
        $origins = config('cors.allowed_origins', []);

        if (is_string($origins)) {
            return array_map('trim', explode(',', $origins));
        }

        return (array) $origins;
    }

    /**
     * Check if origin matches a pattern (e.g., *.example.com).
     */
    private function isOriginAllowedByPattern(?string $origin, array $allowedOrigins): bool
    {
        if (!$origin) {
            return false;
        }

        foreach ($allowedOrigins as $pattern) {
            if (str_contains($pattern, '*')) {
                $regex = str_replace('*', '.*', preg_quote($pattern, '/'));
                if (preg_match("/{$regex}/i", $origin)) {
                    return true;
                }
            }
        }

        return false;
    }
}
