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
        'import_key' => env('LEXILINGO_IMPORT_KEY'),
        'ai_service_secret' => env('LEXILINGO_AI_SERVICE_SECRET'),
        'timeout' => (int) env('LEXILINGO_TIMEOUT', 30),
        'ai_retry_times' => (int) env('LEXILINGO_AI_RETRY_TIMES', 2),
        'ai_retry_delay_ms' => (int) env('LEXILINGO_AI_RETRY_DELAY_MS', 200),
        'max_audio_kb' => (int) env('LEXILINGO_AI_MAX_AUDIO_KB', 10240),
    ],
];
