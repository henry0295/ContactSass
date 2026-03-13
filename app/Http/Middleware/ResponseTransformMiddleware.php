<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ResponseTransformMiddleware
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

        // Only transform JSON responses
        if (!$this->isJsonResponse($response)) {
            return $response;
        }

        /** @var JsonResponse $response */
        $data = $response->getData(true);

        // Transform response to standardized format
        $transformed = $this->transformResponse($data, $response->getStatusCode());

        // Create new response with transformed data
        return response()->json(
            $transformed,
            $response->getStatusCode(),
            $response->headers->all(),
        );
    }

    /**
     * Check if response is JSON.
     */
    private function isJsonResponse(Response $response): bool
    {
        return $response instanceof JsonResponse ||
               str_contains($response->headers->get('Content-Type', ''), 'application/json');
    }

    /**
     * Transform response to standardized format.
     *
     * @param  mixed  $data
     * @return array<string, mixed>
     */
    private function transformResponse(mixed $data, int $statusCode): array
    {
        // Check if response is already in standardized format
        if (is_array($data) && isset($data['success']) && isset($data['data'])) {
            return $data;
        }

        $success = $statusCode >= 200 && $statusCode < 300;

        // If error response (has error key or message key)
        if (is_array($data) && (isset($data['error']) || (isset($data['message']) && $statusCode >= 400))) {
            return [
                'success' => false,
                'error' => $data['error'] ?? $data['message'] ?? 'An error occurred',
                'code' => $data['code'] ?? $this->codeFromStatus($statusCode),
                'timestamp' => now()->toIso8601String(),
            ];
        }

        // Standard response format
        return [
            'success' => $success,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get error code from HTTP status.
     */
    private function codeFromStatus(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'INVALID_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'UNPROCESSABLE_ENTITY',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'ERROR',
        };
    }
}
