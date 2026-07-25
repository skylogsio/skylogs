<?php

use App\Enums\ClusterType;
use App\Jobs\AddChecksJob;
use App\Jobs\AutoResolveApiAlertsJob;
use App\Jobs\CheckPrometheusJob;
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
