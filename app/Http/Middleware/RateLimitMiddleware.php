<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RedisTokenBucketRateLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RateLimitMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private readonly RedisTokenBucketRateLimiter $rateLimiter,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $limit  Rate limit in format: "requests/seconds" (e.g., "100/60")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $limit = '1000/60'): Response
    {
        $identifier = $this->getIdentifier($request);
        [$requests, $period] = $this->parseLimit($limit);

        // Check rate limit
        $allowed = $this->rateLimiter->allow(
            key: $identifier,
            requests: $requests,
            period: $period,
        );

        if (!$allowed) {
            return response()->json([
                'message' => 'Too many requests',
                'code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $this->rateLimiter->getRetryAfter($identifier),
            ], 429)
                ->header('Retry-After', (string) $this->rateLimiter->getRetryAfter($identifier));
        }

        /** @var Response $response */
        $response = $next($request);

        // Add rate limit headers
        $remaining = $this->rateLimiter->remaining($identifier, $requests);
        $response->header('X-RateLimit-Limit', (string) $requests);
        $response->header('X-RateLimit-Remaining', (string) max(0, $remaining));
        $response->header('X-RateLimit-Reset', (string) $this->rateLimiter->resetAt($identifier));

        return $response;
    }

    /**
     * Get unique identifier for rate limiting.
     */
    private function getIdentifier(Request $request): string
    {
        // Prioritize authenticated user
        if ($request->user()) {
            return "user:{$request->user()->id}";
        }

        // Fall back to IP address
        return "ip:{$request->ip()}";
    }

    /**
     * Parse rate limit string.
     *
     * @return array<int, int>
     */
    private function parseLimit(string $limit): array
    {
        [$requests, $period] = explode('/', $limit);

        return [(int) $requests, (int) $period];
    }
}
