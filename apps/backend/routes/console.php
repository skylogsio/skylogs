<?php

use App\Enums\ClusterType;
use App\Jobs\AddChecksJob;
use App\Jobs\AutoResolveApiAlertsJob;
use App\Jobs\CheckPrometheusJob;
use App\Jobs\Ha\ReconcileHaStateJob;
use App\Jobs\Ha\SyncHaConfigJob;
use App\Jobs\Ha\SyncHaHistoryJob;
use App\Jobs\RefreshStatusHistoryJob;
use App\Jobs\SyncCluster;
use App\Services\ClusterService;
use App\Services\Ha\HaLeaderService;

/*
| Evaluation and notification are the leader's alone: two nodes checking the
| same alert rule would page on-call staff twice. Resolves to true on every
| node while HA is disabled.
*/
$onLeader = fn (): bool => app(HaLeaderService::class)->shouldRunLeaderWork();

Artisan::command('app:test', function () {
    if (config('app.env') === 'local') {

    }
})->purpose('Run Code');

Artisan::command('app:sync-data', function () {
    if (app(ClusterService::class)->type() == ClusterType::AGENT) {
        SyncCluster::dispatch();
    }
})->purpose('Sync Data With Main Cluster');

Schedule::call(function () {
    if (app(ClusterService::class)->type() == ClusterType::AGENT) {
        SyncCluster::dispatch();
    }
})->everyTenMinutes()->when($onLeader);

Schedule::job(new CheckPrometheusJob)->everyFiveSeconds()->when($onLeader);
Schedule::job(new AddChecksJob)->everyFiveSeconds()->when($onLeader);
Schedule::job(new AutoResolveApiAlertsJob)->everyFiveSeconds()->when($onLeader);

/*
| Deliberately not leader-only: refreshing the status history is a local,
| side-effect-free derivation of AlertRule.state that notifies nobody, so
| running it everywhere keeps a follower's dashboard correct without pushing a
| five second time series through Raft.
*/
Schedule::job(new RefreshStatusHistoryJob)->everyFiveSeconds();

/*
| Also on every node, and for the same reason in reverse: a follower catching up
| on deliveries it missed is the whole point, and a leader uses the same pass to
| repair publishes the sidecar dropped.
*/
Schedule::job(new ReconcileHaStateJob)->everyMinute()->when(fn (): bool => (bool) config('ha.enabled'));

/*
|| Followers pull the leader's configuration; the job no-ops on the leader. Every
|| thirty seconds because the version check costs a few bytes when nothing moved,
|| which keeps the lag on a new alert rule to half a minute for almost nothing.
*/
Schedule::job(new SyncHaConfigJob)
    ->everyThirtySeconds()
    ->when(fn (): bool => (bool) config('ha.enabled') && (bool) config('ha.config_sync.enabled'));

/*
| Followers pull alert history and Notify documents in pages. Same interval as
| config sync; catch-up is bounded by HA_HISTORY_SYNC_MAX_PAGES_PER_TICK.
*/
Schedule::job(new SyncHaHistoryJob)
    ->everyThirtySeconds()
    ->when(fn (): bool => (bool) config('ha.enabled') && (bool) config('ha.history_sync.enabled'));
