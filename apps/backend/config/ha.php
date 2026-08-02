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

    'node_id' => env('HA_NODE_ID', 'node1'),

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
    | Backend base URL per Raft node id (must match RAFT_NODE_ID / HA_NODE_ID).
    | /leader returns leaderNode; this map turns that id into a backend URL for
    | config and history sync. Comma separated key=url pairs:
    |
    | HA_PEER_URLS=node1=http://172.28.7.11:8083,node2=http://172.28.7.12:8083,node3=http://172.28.7.13:8083
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

    /*
    |--------------------------------------------------------------------------
    | History / notify pull (follower → leader)
    |--------------------------------------------------------------------------
    |
    | Incremental pages of alert timeline documents and Notify rows. Separate
    | from config_sync so archives can be multi‑GB without bloating every
    | configuration snapshot. Catch-up is bounded per tick by page_size and
    | max_pages_per_tick.
    |
    */
    'history_sync' => [

        'enabled' => (bool) env('HA_HISTORY_SYNC_ENABLED', true),

        'connect_timeout' => (float) env('HA_HISTORY_SYNC_CONNECT_TIMEOUT', 2),

        'timeout' => (float) env('HA_HISTORY_SYNC_TIMEOUT', 30),

        'page_size' => (int) env('HA_HISTORY_SYNC_PAGE_SIZE', 500),

        'max_pages_per_tick' => (int) env('HA_HISTORY_SYNC_MAX_PAGES_PER_TICK', 5),
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
