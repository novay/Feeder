<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    */

    'default' => env('FEEDER_CONNECTION', 'live'),

    /*
    |--------------------------------------------------------------------------
    | Shared Token Defaults
    |--------------------------------------------------------------------------
    |
    | {connection} akan otomatis diganti menjadi nama connection.
    | Contoh:
    | feeder:live:token
    | feeder:sandbox:token
    |
    */

    'token' => [
        'cache_key' => env('FEEDER_TOKEN_CACHE_KEY', 'feeder:{connection}:token'),
        'ttl' => (int) env('FEEDER_TOKEN_TTL', 3600),
        'leeway' => (int) env('FEEDER_TOKEN_LEEWAY', 60),

        'lock_key' => env('FEEDER_TOKEN_LOCK_KEY', 'feeder:{connection}:token:lock'),
        'lock_seconds' => (int) env('FEEDER_TOKEN_LOCK_SECONDS', 10),
        'lock_wait_seconds' => (int) env('FEEDER_TOKEN_LOCK_WAIT_SECONDS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared HTTP Defaults
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => (int) env('FEEDER_TIMEOUT', 30),
        'connect_timeout' => (int) env('FEEDER_CONNECT_TIMEOUT', 10),
        'as_form' => env('FEEDER_AS_FORM', true),

        'retry' => [
            'times' => (int) env('FEEDER_RETRY_TIMES', 2),
            'sleep' => (int) env('FEEDER_RETRY_SLEEP', 300),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Safe Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('FEEDER_LOGGING_ENABLED', true),
        'channel' => env('FEEDER_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Refresh Token
    |--------------------------------------------------------------------------
    */

    'auto_refresh_token' => env('FEEDER_AUTO_REFRESH_TOKEN', true),

    'token_error_keywords' => [
        'token',
        'expired',
        'expire',
        'invalid',
        'kadaluarsa',
        'kedaluwarsa',
        'tidak valid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [
        'live' => [
            'endpoint' => env('FEEDER_LIVE_ENDPOINT', env('FEEDER_ENDPOINT', 'http://185.201.9.10:2200/ws/live2.php')),
            'username' => env('FEEDER_LIVE_USERNAME', env('FEEDER_USERNAME')),
            'password' => env('FEEDER_LIVE_PASSWORD', env('FEEDER_PASSWORD')),
        ],

        'sandbox' => [
            'endpoint' => env('FEEDER_SANDBOX_ENDPOINT'),
            'username' => env('FEEDER_SANDBOX_USERNAME'),
            'password' => env('FEEDER_SANDBOX_PASSWORD'),

            /*
            |--------------------------------------------------------------------------
            | Optional Override
            |--------------------------------------------------------------------------
            |
            | Bagian ini opsional. Jika tidak diisi, sandbox akan memakai default
            | dari konfigurasi global di atas.
            |
            */

            // 'token' => [
            //     'cache_key' => 'feeder:sandbox:token',
            // ],

            // 'http' => [
            //     'timeout' => 60,
            // ],
        ],
    ],
];