<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateApiVersionMiddleware
{
    /**
     * Supported API versions.
     *
     * @var array<int, string>
     */
    private const SUPPORTED_VERSIONS = ['1.0', '2.0'];

    /**
     * Current API version.
     */
    private const CURRENT_VERSION = '2.0';

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $version = $this->getApiVersion($request);

        // Validate version is supported
        if (!in_array($version, self::SUPPORTED_VERSIONS, true)) {
            return response()->json([
                'message' => "API version '{$version}' is not supported",
                'code' => 'UNSUPPORTED_API_VERSION',
                'supported_versions' => self::SUPPORTED_VERSIONS,
                'current_version' => self::CURRENT_VERSION,
            ], 400);
        }

        // Attach version to request
        $request->merge(['api_version' => $version]);

        /** @var Response $response */
        $response = $next($request);

        // Add version header to response
        $response->header('API-Version', $version);

        return $response;
    }

    /**
     * Get API version from request.
     */
    private function getApiVersion(Request $request): string
    {
        // Check Accept header
        $accept = $request->header('Accept');
        if ($accept && preg_match('/application\/vnd\.api\+json;\s*version=([0-9.]+)/i', $accept, $matches)) {
            return $matches[1];
        }

        // Check X-API-Version header
        if ($request->header('X-API-Version')) {
            return $request->header('X-API-Version');
        }

        // Check query parameter
        if ($request->has('api_version')) {
            return $request->input('api_version');
        }

        // Default to current version
        return self::CURRENT_VERSION;
    }
}
