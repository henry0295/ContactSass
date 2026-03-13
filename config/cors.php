<?php

return [
    /**
     * Allowed origins for CORS requests.
     * Can be a comma-separated string or array of origins.
     * Use '*' to allow all origins (not recommended for production).
     * Use patterns like '*.example.com' to allow subdomains.
     */
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:8080')),

    /**
     * Allowed HTTP methods.
     */
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /**
     * Allowed headers.
     */
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-Request-ID',
        'X-Tenant-ID',
        'Accept',
        'Accept-Language',
    ],

    /**
     * Headers that can be exposed to the browser.
     */
    'exposed_headers' => [
        'X-Request-ID',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'API-Version',
    ],

    /**
     * Whether credentials (cookies, authorization headers, etc.) are allowed.
     */
    'allow_credentials' => true,

    /**
     * Maximum age for preflight requests (in seconds).
     */
    'max_age' => 86400,
];
