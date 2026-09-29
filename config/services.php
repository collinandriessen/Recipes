<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // USDA FoodData Central — Phase 2.1 (SAA-16). Free API, get a key at
    // https://fdc.nal.usda.gov/api-key-signup.html (DEMO_KEY works for
    // light testing but is shared/heavily throttled — use a real key in
    // any shared environment).
    'fdc' => [
        'api_key' => env('FDC_API_KEY'),
        'rate_limit_per_hour' => env('FDC_RATE_LIMIT_PER_HOUR', 1000),
        'circuit_failure_threshold' => env('FDC_CIRCUIT_FAILURE_THRESHOLD', 5),
        'circuit_cooldown_seconds' => env('FDC_CIRCUIT_COOLDOWN_SECONDS', 60),
        'max_attempts' => env('FDC_MAX_ATTEMPTS', 3),
        'base_backoff_ms' => env('FDC_BASE_BACKOFF_MS', 250),
        'timeout_seconds' => env('FDC_TIMEOUT_SECONDS', 10),
    ],

];
