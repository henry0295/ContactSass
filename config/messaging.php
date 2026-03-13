<?php

return [
    'batch_size' => env('CAMPAIGN_BATCH_SIZE', 1000),

    'aws' => [
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'ses_from' => env('SES_DEFAULT_FROM', 'noreply@contactsass.com'),
        'sns_sender_id' => env('SNS_DEFAULT_SENDER_ID', 'ContactSass'),
    ],

    'freeswitch' => [
        'host' => env('FREESWITCH_HOST', '127.0.0.1'),
        'port' => (int) env('FREESWITCH_PORT', 8021),
        'password' => env('FREESWITCH_PASSWORD', 'ClueCon'),
    ],

    'rate_limits' => [
        'emails_per_minute' => env('RATE_LIMIT_EMAILS', 1000),
        'sms_per_minute' => env('RATE_LIMIT_SMS', 600),
        'calls_per_minute' => env('RATE_LIMIT_CALLS', 120),
    ],

    'webhooks' => [
        'verify_signature' => env('VERIFY_WEBHOOK_SIGNATURE', true),
        'ses_topic_arn' => env('SES_SNS_TOPIC_ARN'),
        'sns_topic_arn' => env('SNS_TOPIC_ARN'),
    ],
];

