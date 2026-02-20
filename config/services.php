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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'sync' => [
        'upload_endpoint' => env('SYNC_UPLOAD_ENDPOINT'),
        'shared_secret' => env('SHARED_UPLOAD_SECRET'),
        'signature_ttl_seconds' => env('SYNC_SIGNATURE_TTL_SECONDS', 300),
    ],

    'whisper' => [
        'python_path' => env('WHISPER_PYTHON_PATH', base_path('.venv/bin/python')),
        'script_path' => env('WHISPER_SCRIPT_PATH', base_path('scripts/transcribe.py')),
        'model' => env('WHISPER_MODEL', 'large-v3-turbo'),
        'bin_path' => env('WHISPER_BIN_PATH'),
        'model_path' => env('WHISPER_MODEL_PATH'),
    ],

];
