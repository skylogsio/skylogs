<?php

return [

    /*
    |--------------------------------------------------------------------------
    | High Availability
    |--------------------------------------------------------------------------
    |
    | When disabled the node behaves exactly like a single node install: no
    | leader detection, no replication, and every scheduled job runs locally.
    |
    */

    'enabled' => (bool) env('HA_ENABLED', false),

    'node_id' => env('HA_NODE_ID', 'node-1'),

    /*
    | Shared secret the local Raft sidecar sends as X-Skylogs-HA-Secret when it
    | calls POST /api/ha/apply on this node.
    */
    'node_secret' => env('HA_NODE_SECRET', ''),

    /*
    | Optional defence in depth for the HA endpoints. Every source IP is
    | accepted while this list is empty.
    */
    'allowed_cidrs' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('HA_ALLOWED_CIDRS', '')))
    )),

    /*
    | How long a /leader answer stays cached. Keep it well below the scheduler
    | tick so a failover is picked up within a couple of seconds while a five
    | second tick still costs at most one sidecar call.
    */
    'leader_cache_seconds' => (int) env('HA_LEADER_CACHE_SECONDS', 2),

    'state_retention_days' => (int) env('HA_STATE_RETENTION_DAYS', 7),

    'raft' => [

        'url' => env('RAFT_URL', 'http://raft:8090'),

        'connect_timeout' => (float) env('RAFT_CONNECT_TIMEOUT', 0.5),

        /*
        | Per endpoint request timeouts, in seconds. /leader is polled on every
        | scheduler tick so it is kept short; /save and /state carry payloads.
        */
        'timeout' => [
            'leader' => (float) env('RAFT_LEADER_TIMEOUT', 1),
            'save' => (float) env('RAFT_SAVE_TIMEOUT', 3),
            'state' => (float) env('RAFT_STATE_TIMEOUT', 3),
        ],

        /*
        | Total attempts, not extra attempts: 2 means a single retry.
        */
        'retry_attempts' => (int) env('RAFT_RETRY_ATTEMPTS', 2),

        'retry_sleep_milliseconds' => (int) env('RAFT_RETRY_SLEEP_MILLISECONDS', 100),
    ],

];
