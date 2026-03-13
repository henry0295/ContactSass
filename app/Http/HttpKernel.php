<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\RequestLoggingMiddleware;
use App\Http\Middleware\ResponseTransformMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ValidateApiVersionMiddleware;
use App\Http\Middleware\ValidateTenantMiddleware;
use App\Http\Middleware\ValidateUserPermissionsMiddleware;

final class HttpKernel
{
    /**
     * Global HTTP middleware that runs on every request.
     *
     * @var array<class-string>
     */
    protected array $middleware = [
        // Request/Response logging and tracking
        RequestLoggingMiddleware::class,

        // Security headers
        SecurityHeaders::class,

        // CORS handling
        CorsMiddleware::class,

        // API version validation
        ValidateApiVersionMiddleware::class,

        // Rate limiting
        RateLimitMiddleware::class,

        // Response transformation
        ResponseTransformMiddleware::class,
    ];

    /**
     * Route-specific middleware groups.
     *
     * @var array<string, array<class-string>>
     */
    protected array $middlewareGroups = [
        'api' => [
            'throttle:api',
            'bindings',
        ],

        'api.v1' => [
            'api',
        ],

        'api.v2' => [
            'api',
        ],

        'tenant' => [
            ValidateTenantMiddleware::class,
        ],

        'auth' => [
            'auth:sanctum',
        ],

        'admin' => [
            ValidateUserPermissionsMiddleware::class . ':users.manage,settings.configure',
        ],
    ];

    /**
     * Route-specific middleware.
     *
     * @var array<string, class-string>
     */
    protected array $routeMiddleware = [
        'cors' => CorsMiddleware::class,
        'rate.limit' => RateLimitMiddleware::class,
        'validate.tenant' => ValidateTenantMiddleware::class,
        'validate.permissions' => ValidateUserPermissionsMiddleware::class,
        'log.requests' => RequestLoggingMiddleware::class,
        'security.headers' => SecurityHeaders::class,
        'validate.api.version' => ValidateApiVersionMiddleware::class,
        'transform.response' => ResponseTransformMiddleware::class,
    ];

    /**
     * Get global middleware.
     *
     * @return array<class-string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get middleware groups.
     *
     * @return array<string, array<class-string>>
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Get route-specific middleware.
     *
     * @return array<string, class-string>
     */
    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
    }
}
