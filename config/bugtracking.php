<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reporting toggle
    |--------------------------------------------------------------------------
    |
    | When disabled, exceptions are still handled by Laravel but never sent
    | to the Go BugTrack API.
    |
    */

    'enabled' => env('BUGTRACK_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Ingest connection
    |--------------------------------------------------------------------------
    |
    | Preferred: a DSN from the BugTrack project page, for example
    | http://<project-key>@127.0.0.1:8080/<project-id>
    |
    | Or set URL + key separately. BUG_TRCAK_KEY is kept as a fallback for
    | existing .env files from the Python-era package.
    |
    */

    'dsn' => env('BUGTRACK_DSN'),

    'url' => env('BUGTRACK_URL'),

    'key' => env('BUGTRACK_KEY', env('BUG_TRCAK_KEY')),

    'environment' => env('BUGTRACK_ENVIRONMENT', env('APP_ENV', 'production')),

    /*
    |--------------------------------------------------------------------------
    | Ignored exceptions
    |--------------------------------------------------------------------------
    |
    | Extra exception classes to skip. Validation, auth, CSRF, and HTTP
    | responses below 500 are always ignored.
    |
    */

    'ignore' => [],

    'connect_timeout_ms' => env('BUGTRACK_CONNECT_TIMEOUT_MS', 2000),

    'timeout_ms' => env('BUGTRACK_TIMEOUT_MS', 3000),

];
