<?php

declare(strict_types=1);

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

    'authorizer' => [
        'url' => env('AUTHORIZER_URL', 'https://util.devi.tools/api/v2/authorize'),
        'timeout' => (int) env('AUTHORIZER_TIMEOUT', 10),
        'retry_times' => (int) env('AUTHORIZER_RETRY_TIMES', 2),
        'retry_backoff_ms' => (int) env('AUTHORIZER_RETRY_BACKOFF_MS', 2000),
    ],

    'notifier' => [
        'url' => env('NOTIFIER_URL', 'https://util.devi.tools/api/v1/notify'),
        'timeout' => (int) env('NOTIFIER_TIMEOUT', 10),
    ],

];
