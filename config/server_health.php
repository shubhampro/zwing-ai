<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Named connection targets
    |--------------------------------------------------------------------------
    |
    | Checked sequentially. Use "default" for the app's default connection name
    | at runtime. Avoid listing every org tenant DB here — that causes spikes.
    |
    */

    'targets' => [
        'default',
        'mysql_ssh',
        'mongodb_ssh',
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeouts (seconds)
    |--------------------------------------------------------------------------
    */

    'connect_timeout' => (int) env('SERVER_HEALTH_CONNECT_TIMEOUT', 2),

    'query_timeout' => (int) env('SERVER_HEALTH_QUERY_TIMEOUT', 2),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache_key' => 'server_health.snapshot',

    'lock_key' => 'server_health.check',

    'cache_ttl_seconds' => (int) env('SERVER_HEALTH_CACHE_TTL', 60),

    'lock_seconds' => (int) env('SERVER_HEALTH_LOCK_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Threads_running thresholds (MySQL)
    |--------------------------------------------------------------------------
    */

    'thresholds' => [
        'threads_running_warn' => (int) env('SERVER_HEALTH_THREADS_WARN', 50),
        'threads_running_critical' => (int) env('SERVER_HEALTH_THREADS_CRITICAL', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | History
    |--------------------------------------------------------------------------
    */

    'history_limit' => (int) env('SERVER_HEALTH_HISTORY_LIMIT', 20),

];
