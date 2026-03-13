<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequestLoggingMiddleware
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
        // Generate unique request ID
        $requestId = $this->generateRequestId($request);
        $request->merge(['request_id' => $requestId]);

        // Log incoming request
        $this->logRequest($request, $requestId);

        $startTime = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        // Calculate execution time
        $duration = microtime(true) - $startTime;

        // Log response
        $this->logResponse($response, $requestId, $duration);

        // Add request ID to response header
        $response->header('X-Request-ID', $requestId);

        return $response;
    }

    /**
     * Generate unique request ID.
     */
    private function generateRequestId(Request $request): string
    {
        $providedId = $request->header('X-Request-ID');

        if ($providedId) {
            return $providedId;
        }

        return Str::ulid()->toString();
    }

    /**
     * Log incoming request.
     */
    private function logRequest(Request $request, string $requestId): void
    {
        $logData = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'url' => $request->url(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ];

        if ($request->user()) {
            $logData['user_id'] = $request->user()->id;
        }

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            $logData['content_length'] = $request->header('Content-Length') ?? 'unknown';
        }

        \Log::info('Incoming Request', $logData);
    }

    /**
     * Log response.
     */
    private function logResponse(Response $response, string $requestId, float $duration): void
    {
        $logData = [
            'request_id' => $requestId,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => round($duration * 1000, 2),
            'timestamp' => now()->toIso8601String(),
        ];

        if ($response->headers->has('Content-Length')) {
            $logData['response_size'] = $response->headers->get('Content-Length');
        }

        // Determine log level based on status code
        $level = $this->getLogLevel((int) $response->getStatusCode());

        \Log::logEntry($level, 'Response', $logData);
    }

    /**
     * Get appropriate log level based on status code.
     */
    private function getLogLevel(int $statusCode): string
    {
        if ($statusCode >= 500) {
            return 'error';
        }

        if ($statusCode >= 400) {
            return 'warning';
        }

        return 'info';
    }
}
