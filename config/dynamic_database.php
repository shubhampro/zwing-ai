<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SQL read-only guard (dynamic connections)
    |--------------------------------------------------------------------------
    |
    | When enabled, mutating SQL on connections registered from the
    | database_connections table is blocked when the row is access_mode=read
    | and enforce_read_only_sql_guard=true (MySQL and PostgreSQL only).
    |
    */

    'read_only_guard_enabled' => env('DYNAMIC_DB_READ_ONLY_GUARD_ENABLED', true),

    /*
    | Runtime keys populated by DatabaseConnectionRegistrar.
    |
    | @var list<string>
    */
    'registered_slugs' => [],

    /*
    | @var list<string>
    */
    'read_only_guarded_slugs' => [],

];
