<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP limits for scheduled data-source polls
    |--------------------------------------------------------------------------
    |
    | A down source must not hold a worker for the HTTP client's default
    | timeout. These match the health-check caps.
    |
    */

    'connect_timeout' => (int) env('DATASOURCE_CHECK_CONNECT_TIMEOUT', 2),

    'timeout' => (int) env('DATASOURCE_CHECK_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Unreachable cooldown
    |--------------------------------------------------------------------------
    |
    | After a connection failure, skip that source until this many seconds
    | have passed instead of opening a new socket on every rule.
    |
    */

    'cooldown_seconds' => (int) env('DATASOURCE_CHECK_COOLDOWN_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Unique lock
    |--------------------------------------------------------------------------
    |
    | How long a per-rule check keeps its unique lock if the worker dies
    | before the job finishes. Longer than the HTTP timeout, shorter than
    | a manual retry would want to wait.
    |
    */

    'unique_for' => (int) env('DATASOURCE_CHECK_UNIQUE_FOR', 30),

];
