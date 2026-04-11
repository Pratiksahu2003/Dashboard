<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    /*
    | Used by BroadcastingAuthProxyController. Read via config() so values stay
    | correct when production runs `php artisan config:cache` (raw env() in app code can be empty).
    */
    'suganta' => [
        'api_origin' => rtrim(env('VITE_API_ORIGIN', 'https://api.suganta.com'), '/'),
        /** Path only, e.g. /api/v1/auth/user or /auth/user — must match the API route that reads the web session. */
        'auth_user_path' => env('SUGANTA_AUTH_USER_PATH', '/api/v1/auth/user'),
        'reverb_app_key' => env('REVERB_APP_KEY') ?: env('VITE_REVERB_APP_KEY'),
        'reverb_app_secret' => env('REVERB_APP_SECRET'),
    ],

];
