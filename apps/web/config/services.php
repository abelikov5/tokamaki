<?php

return [

    'filament_admin' => [
        'email' => env('FILAMENT_ADMIN_EMAIL', 'abelikov@inbox.ru'),
        'password' => env('FILAMENT_ADMIN_PASSWORD'),
    ],

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

    'generator' => [
        'url' => rtrim(env('GENERATOR_URL', 'http://127.0.0.1:8001'), '/'),
        'timeout' => (int) env('GENERATOR_TIMEOUT', 300),
    ],

    'catalog_api' => [
        'token' => env('CATALOG_API_TOKEN'),
    ],

    'mockup_catalog' => [
        'url' => rtrim(env('MOCKUP_CATALOG_URL', 'https://mockup.n8n-team.ru'), '/'),
        'token' => env('MOCKUP_CATALOG_TOKEN'),
    ],

    'aitunnel' => [
        'key' => env('AITUNNEL_API_KEY'),
        'api_url' => env('AITUNNEL_API_URL', 'https://api.aitunnel.ru/v1/chat/completions'),
        'poster_model' => env('AITUNNEL_POSTER_MODEL', 'qwen3.7-flash'),
        'timeout' => (int) env('AITUNNEL_TIMEOUT', 120),
    ],

    'indexnow' => [
        'endpoint' => env('INDEXNOW_ENDPOINT', 'https://yandex.com/indexnow'),
        'key' => env('INDEXNOW_KEY', 'tokamaki-1c8f72e6a4b945e3b8d2f0a769c4315e'),
    ],

];
