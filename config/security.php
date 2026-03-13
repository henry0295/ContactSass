<?php

return [
    /**
     * Security audit logging configuration.
     */
    'audit' => [
        /**
         * Enable security audit logging.
         * 
         * When enabled, all security-relevant events are logged to the database
         * and can be queried for compliance and forensics.
         */
        'enabled' => env('SECURITY_AUDIT_ENABLED', true),

        /**
         * Retention period in days.
         * 
         * Audit logs older than this will be archived or deleted.
         */
        'retention_days' => env('SECURITY_AUDIT_RETENTION_DAYS', 90),

        /**
         * Events to log.
         */
        'events' => [
            'authentication' => true,           // Login, logout, failed attempts
            'authorization' => true,             // Permission checks, denials
            'resource_changes' => true,          // Create, update, delete operations
            'sensitive_operations' => true,      // Password changes, API key generation
            'security_events' => true,           // Rate limits, CSRF failures, etc
            'field_encryption' => true,          // Sensitive field access
            'admin_actions' => true,             // Admin-only operations
        ],

        /**
         * Sensitive fields to redact in logs.
         */
        'redact_fields' => [
            'password', 'pin', 'secret', 'token', 'api_key',
            'authorization', 'bearer', 'credit_card', 'cvv',
            'ssn', 'social_security', 'private_key', 'public_key',
        ],

        /**
         * Alert configuration.
         */
        'alerts' => [
            'enabled' => env('SECURITY_AUDIT_ALERTS_ENABLED', true),
            'channel' => env('SECURITY_AUDIT_ALERT_CHANNEL', 'mail'),
            'recipients' => explode(',', env('SECURITY_ALERT_RECIPIENTS', 'security@example.com')),

            /**
             * Alert triggers.
             */
            'triggers' => [
                'failed_login_attempts' => 5,           // Alert after N failed logins
                'authorization_failures' => 10,         // Alert after N permission denials
                'admin_actions' => true,                // Alert on all admin actions
                'sensitive_operations' => true,         // Alert on password changes, etc
                'rate_limit_exceeded' => 100,           // Alert after N rate limit hits
                'unusual_ip' => true,                   // Alert on login from new IP
            ],
        ],
    ],

    /**
     * CSRF protection configuration.
     */
    'csrf' => [
        'enabled' => env('CSRF_PROTECTION_ENABLED', true),
        'token_name' => env('CSRF_TOKEN_NAME', 'X-CSRF-Token'),
        'header_name' => env('CSRF_HEADER_NAME', 'X-CSRF-Token'),
        'exclude_paths' => [
            'api/*/webhooks/*',
            'api/webhooks/*',
            'health',
        ],
    ],

    /**
     * Webhook signature verification configuration.
     */
    'webhooks' => [
        'enabled' => env('WEBHOOK_VERIFICATION_ENABLED', true),

        'providers' => [
            'amazon-ses' => [
                'algorithm' => 'sha256',
                'secret_env' => 'WEBHOOK_AMAZON_SES_SECRET',
                'headers' => ['X-Signature', 'Signature'],
            ],
            'amazon-sns' => [
                'algorithm' => 'sha256',
                'secret_env' => 'WEBHOOK_AMAZON_SNS_SECRET',
                'headers' => ['X-Signature', 'X-Amz-SNS-Message-Id'],
            ],
            'freeswitch' => [
                'algorithm' => 'sha256',
                'secret_env' => 'WEBHOOK_FREESWITCH_SECRET',
                'headers' => ['X-Freeswitch-Signature', 'X-Signature'],
            ],
        ],

        /**
         * Retry configuration for failed webhook processing.
         */
        'retries' => [
            'enabled' => true,
            'max_attempts' => 3,
            'backoff_seconds' => 300, // 5 minutes
        ],

        /**
         * Rate limiting for webhook endpoints.
         */
        'rate_limit' => [
            'enabled' => true,
            'requests_per_minute' => 1000,
        ],
    ],

    /**
     * HTTPS/HSTS configuration.
     */
    'https' => [
        /**
         * Force HTTPS in production.
         */
        'enforce' => env('APP_ENV') === 'production',

        /**
         * HSTS (HTTP Strict Transport Security) configuration.
         */
        'hsts' => [
            'max_age' => 31536000, // 1 year
            'include_subdomains' => true,
            'preload' => true,
        ],

        /**
         * Redirect HTTP to HTTPS.
         */
        'redirect' => env('HTTPS_REDIRECT_ENABLED', true),
    ],

    /**
     * Password policy configuration.
     */
    'password' => [
        'min_length' => env('PASSWORD_MIN_LENGTH', 12),
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_special' => true,
        'history_count' => 5, // Prevent reuse of last N passwords
        'expiry_days' => 90,
        'warning_days' => 14,
    ],

    /**
     * Rate limiting configuration for security features.
     */
    'rate_limits' => [
        'login_attempts' => env('RATE_LIMIT_LOGIN', '5/15'), // 5 attempts per 15 minutes
        'password_reset' => env('RATE_LIMIT_PASSWORD_RESET', '3/60'), // 3 per hour
        'api_key_generation' => env('RATE_LIMIT_API_KEY', '10/60'), // 10 per hour
        'webhook_delivery' => env('RATE_LIMIT_WEBHOOK', '1000/60'), // 1000 per minute
    ],

    /**
     * IP whitelisting configuration.
     */
    'ip_whitelist' => [
        'enabled' => env('IP_WHITELIST_ENABLED', false),
        'mode' => env('IP_WHITELIST_MODE', 'allow'), // 'allow' or 'deny'
        'ips' => explode(',', env('IP_WHITELIST_IPS', '')),
    ],

    /**
     * Session security configuration.
     */
    'session' => [
        'secure_cookies' => env('SESSION_SECURE_COOKIES', true),
        'http_only' => env('SESSION_HTTP_ONLY', true),
        'same_site' => env('SESSION_SAME_SITE', 'lax'),
        'timeout_minutes' => env('SESSION_TIMEOUT_MINUTES', 30),
        'idle_timeout_minutes' => env('SESSION_IDLE_TIMEOUT_MINUTES', 15),
    ],

    /**
     * Content Security Policy configuration.
     */
    'csp' => [
        'enabled' => true,
        'policy' => env('CSP_POLICY', "default-src 'self'; script-src 'self' 'unsafe-inline'"),
        'report_only' => env('CSP_REPORT_ONLY', false),
    ],
];
