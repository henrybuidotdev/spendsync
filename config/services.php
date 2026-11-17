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

    // Fallback rates (VND per unit) used until spendsync:update-exchange-rates has downloaded the daily table.
    'fx' => [
        'usd_vnd' => env('FX_USD_VND', 26000),
    ],

    // A mail sync goes back this many days (from now on the first sync, from last_synced_at after that),
    // then walks forward to today in 15-day windows.
    'mail_sync' => [
        'first_sync_days' => (int) env('MAIL_SYNC_FIRST_SYNC_DAYS', 90),
        'resync_days' => (int) env('MAIL_SYNC_RESYNC_DAYS', 1),
    ],

];
