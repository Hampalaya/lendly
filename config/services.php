<?php

return [
    'listing_geocoder' => [
        'url' => env('LISTING_GEOCODER_URL', 'https://photon.komoot.io/reverse'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost:8000'), '/').'/auth/google/callback'),
    ],

    'face_match' => [
        'python' => env('FACE_MATCH_PYTHON', storage_path('app/private/face-runtime/'.(PHP_OS_FAMILY === 'Windows' ? 'Scripts/python.exe' : 'bin/python'))),
        'models' => env('FACE_MATCH_MODELS', storage_path('app/private/face-models')),
    ],

    'identity' => [
        'base_url' => env('IDENTITY_BASE_URL'),
        'client_id' => env('IDENTITY_CLIENT_ID'),
        'client_secret' => env('IDENTITY_CLIENT_SECRET'),
        'public_key' => env('IDENTITY_PUBLIC_KEY'),
        'sdk_url' => env('IDENTITY_SDK_URL', 'https://hackathon-everify-face-liveness.e.gov.ph/js/everify-liveness-sdk.min.js'),
        'environment' => env('IDENTITY_ENVIRONMENT', 'sandbox'),
    ],

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

];
