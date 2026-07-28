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
    | Backend base URL per node, comma separated. A leader resolves its own URL
    | from this map, and older sidecars may still report peers by Raft address.
    |
    | Key each entry by that node's Raft advertise address, with or without the
    | Raft port. A node id key resolves too, which is how a leader maps itself:
    |
    | HA_PEER_URLS=172.28.7.11=http://nginx_back-1:80,172.28.7.12=http://nginx_back-2:80
    */
    'peers' => (static function (): array {
        $peers = [];

        foreach (explode(',', (string) env('HA_PEER_URLS', '')) as $peer) {
            if (! str_contains($peer, '=')) {
                continue;
            }

            [$node, $url] = array_map('trim', explode('=', $peer, 2));

            if ($node !== '' && $url !== '') {
                $peers[$node] = $url;
            }
        }

        return $peers;
    })(),

    /*
    | How long a /leader answer stays cached. Keep it well below the scheduler
    | tick so a failover is picked up within a couple of seconds while a five
    | second tick still costs at most one sidecar call.
    */
    'leader_cache_seconds' => (int) env('HA_LEADER_CACHE_SECONDS', 2),

    'state_retention_days' => (int) env('HA_STATE_RETENTION_DAYS', 7),

    /*
    | Configuration pull, follower to leader. The timeout is generous because a
    | first sync carries every replicated collection at once; the steady state
    | is a version check that returns a handful of bytes.
    */
    'config_sync' => [

        'enabled' => (bool) env('HA_CONFIG_SYNC_ENABLED', true),

        'connect_timeout' => (float) env('HA_CONFIG_SYNC_CONNECT_TIMEOUT', 2),

        'timeout' => (float) env('HA_CONFIG_SYNC_TIMEOUT', 30),
    ],

    'raft' => [

        'url' => env('RAFT_URL', 'http://raft:8000'),

        'connect_timeout' => (float) env('RAFT_CONNECT_TIMEOUT', 0.5),

        /*
        | Per endpoint request timeouts, in seconds. /leader is polled on every
        | scheduler tick so it is kept short; /set and /get carry payloads.
        */
        'timeout' => [
            'status' => (float) env('RAFT_STATUS_TIMEOUT', 1),
            'set' => (float) env('RAFT_SET_TIMEOUT', 3),
            'get' => (float) env('RAFT_GET_TIMEOUT', 3),
        ],

        /*
        | Total attempts, not extra attempts: 2 means a single retry.
        */
        'retry_attempts' => (int) env('RAFT_RETRY_ATTEMPTS', 2),

        'retry_sleep_milliseconds' => (int) env('RAFT_RETRY_SLEEP_MILLISECONDS', 100),
    ],

];
