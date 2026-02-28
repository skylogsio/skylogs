<?php

use App\Enums\ClusterType;
use App\Jobs\AddChecksJob;
use App\Jobs\AutoResolveApiAlertsJob;
use App\Jobs\CheckPrometheusJob;
use App\Jobs\RefreshStatusHistoryJob;
use App\Jobs\SyncCluster;
use App\Services\ClusterService;
use App\Services\UserService;

Artisan::command('app:test', function () {
    if (config('app.env') === 'local') {
        $user = \App\Models\User::first();
        $this->info($user->id);
        $token = auth()->login($user);
        $this->info($token);

        $a = app(UserService::class)->getUserByMainId($token);
        $this->info($a);
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
})->everyTenMinutes();

Schedule::job(new CheckPrometheusJob)->everyFiveSeconds();
Schedule::job(new AddChecksJob)->everyFiveSeconds();
Schedule::job(new AutoResolveApiAlertsJob)->everyFiveSeconds();
Schedule::job(new RefreshStatusHistoryJob)->everyFiveSeconds();
