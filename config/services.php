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
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],
    'lexilingo' => [
        'backend_url' => env('LEXILINGO_BACKEND_URL'),
        'ai_url' => env('LEXILINGO_AI_URL'),
        'partner_api_key' => env('LEXILINGO_PARTNER_API_KEY'),
        'import_key' => env('LEXILINGO_IMPORT_KEY'),
        'ai_service_secret' => env('LEXILINGO_AI_SERVICE_SECRET'),
        'trace_cag_service_token' => env('LEXILINGO_TRACE_CAG_SERVICE_TOKEN'),
        'subject_hmac_secret' => env('LEXILINGO_SUBJECT_HMAC_SECRET'),
        'timeout' => (int) env('LEXILINGO_TIMEOUT', 30),
        'connect_timeout' => (int) env('LEXILINGO_CONNECT_TIMEOUT', 5),
        'import_delay_ms' => (int) env('LEXILINGO_IMPORT_DELAY_MS', 250),
        'import_max_retries' => (int) env('LEXILINGO_IMPORT_MAX_RETRIES', 3),
        'import_max_backoff_ms' => (int) env('LEXILINGO_IMPORT_MAX_BACKOFF_MS', 10000),
        'ai_retry_times' => (int) env('LEXILINGO_AI_RETRY_TIMES', 2),
        'ai_retry_delay_ms' => (int) env('LEXILINGO_AI_RETRY_DELAY_MS', 200),
        'max_audio_kb' => (int) env('LEXILINGO_MAX_AUDIO_KB', 10240),
    ],
];
