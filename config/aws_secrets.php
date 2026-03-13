<?php

return [
    /**
     * Enable AWS Secrets Manager integration.
     * 
     * When enabled, sensitive configuration will be fetched from AWS Secrets Manager
     * instead of .env file. This is recommended for production environments.
     */
    'enabled' => env('AWS_SECRETS_ENABLED', false),

    /**
     * AWS region where secrets are stored.
     */
    'region' => env('AWS_REGION', 'us-east-1'),

    /**
     * Cache TTL in seconds (1 hour by default).
     * 
     * Secrets are cached locally to reduce API calls to AWS Secrets Manager.
     */
    'cache_ttl' => env('AWS_SECRETS_CACHE_TTL', 3600),

    /**
     * Secrets configuration mapping.
     * 
     * Maps application configuration keys to AWS Secrets Manager secret names.
     */
    'secrets' => [
        // Database
        'db_password' => env('AWS_SECRETS_DB_PASSWORD', 'contact-sass/database/password'),
        'db_root_password' => env('AWS_SECRETS_DB_ROOT_PASSWORD', 'contact-sass/database/root-password'),

        // API Keys
        'aws_access_key_id' => env('AWS_SECRETS_AWS_KEY_ID', 'contact-sass/aws/access-key-id'),
        'aws_secret_access_key' => env('AWS_SECRETS_AWS_SECRET_KEY', 'contact-sass/aws/secret-access-key'),

        // Integrations
        'amazon_ses_key' => env('AWS_SECRETS_SES_KEY', 'contact-sass/integrations/amazon-ses/key'),
        'amazon_ses_secret' => env('AWS_SECRETS_SES_SECRET', 'contact-sass/integrations/amazon-ses/secret'),
        'amazon_sns_key' => env('AWS_SECRETS_SNS_KEY', 'contact-sass/integrations/amazon-sns/key'),
        'amazon_sns_secret' => env('AWS_SECRETS_SNS_SECRET', 'contact-sass/integrations/amazon-sns/secret'),
        'freeswitch_password' => env('AWS_SECRETS_FREESWITCH_PASS', 'contact-sass/integrations/freeswitch/password'),

        // Webhooks
        'webhook_amazon_ses_secret' => env('AWS_SECRETS_WEBHOOK_SES', 'contact-sass/webhooks/amazon-ses/secret'),
        'webhook_amazon_sns_secret' => env('AWS_SECRETS_WEBHOOK_SNS', 'contact-sass/webhooks/amazon-sns/secret'),
        'webhook_freeswitch_secret' => env('AWS_SECRETS_WEBHOOK_FREESWITCH', 'contact-sass/webhooks/freeswitch/secret'),

        // JWT/Auth
        'jwt_secret' => env('AWS_SECRETS_JWT', 'contact-sass/auth/jwt-secret'),
        'sanctum_secret' => env('AWS_SECRETS_SANCTUM', 'contact-sass/auth/sanctum-secret'),

        // Redis
        'redis_password' => env('AWS_SECRETS_REDIS', 'contact-sass/redis/password'),
    ],

    /**
     * Rotation configuration.
     * 
     * Automatic rotation schedule for sensitive secrets.
     */
    'rotation' => [
        'enabled' => env('AWS_SECRETS_ROTATION_ENABLED', false),
        'schedule' => env('AWS_SECRETS_ROTATION_SCHEDULE', '0 0 1 * *'), // Monthly
        'days_before_expiry' => env('AWS_SECRETS_ROTATION_DAYS_BEFORE', 7),
    ],

    /**
     * Audit and logging configuration.
     */
    'audit' => [
        'log_retrievals' => env('AWS_SECRETS_LOG_RETRIEVALS', false),
        'alert_on_rotation' => env('AWS_SECRETS_ALERT_ON_ROTATION', true),
        'alert_on_access_denied' => env('AWS_SECRETS_ALERT_ON_DENIED', true),
    ],
];
