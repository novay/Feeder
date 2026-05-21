<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feeder Endpoint
    |--------------------------------------------------------------------------
    */

    'endpoint' => env('FEEDER_ENDPOINT', 'http://185.201.9.10:2200/ws/live2.php'),

    /*
    |--------------------------------------------------------------------------
    | Feeder Credential
    |--------------------------------------------------------------------------
    */

    'username' => env('FEEDER_USERNAME'),
    'password' => env('FEEDER_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Token Cache
    |--------------------------------------------------------------------------
    */

    'token' => [
        'cache_key' => env('FEEDER_TOKEN_CACHE_KEY', 'feeder:token'),

        // Dipakai bila token JWT tidak memiliki klaim exp.
        'ttl' => (int) env('FEEDER_TOKEN_TTL', 3600),

        // Token dianggap hampir kedaluwarsa sekian detik sebelum exp.
        'leeway' => (int) env('FEEDER_TOKEN_LEEWAY', 60),

        // Nama lock agar request paralel tidak mengambil token bersamaan.
        'lock_key' => env('FEEDER_TOKEN_LOCK_KEY', 'feeder:token:lock'),

        // Durasi lock.
        'lock_seconds' => (int) env('FEEDER_TOKEN_LOCK_SECONDS', 10),

        // Maksimal menunggu lock.
        'lock_wait_seconds' => (int) env('FEEDER_TOKEN_LOCK_WAIT_SECONDS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Options
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => (int) env('FEEDER_TIMEOUT', 30),
        'connect_timeout' => (int) env('FEEDER_CONNECT_TIMEOUT', 10),

        // Feeder umumnya menerima application/x-www-form-urlencoded.
        'as_form' => env('FEEDER_AS_FORM', true),
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
];
