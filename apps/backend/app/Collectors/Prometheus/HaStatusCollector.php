<?php

namespace App\Collectors\Prometheus;

use App\Exceptions\Ha\RaftUnavailableException;
use App\Services\Ha\RaftClient;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;
use Throwable;

class HaStatusCollector implements Collector
{
    public function register(): void
    {
        Prometheus::addGauge('HA enabled')
            ->name('ha_enabled')
            ->helpText('1 when HA is enabled on this node, otherwise 0')
            ->value(fn (): float => config('ha.enabled') ? 1.0 : 0.0);

        Prometheus::addGauge('Raft up')
            ->name('raft_up')
            ->helpText('1 when HA is enabled and the local Raft sidecar answers cluster status, otherwise 0')
            ->value(fn (): float => $this->raftUp());
    }

    private function raftUp(): float
    {
        if (! config('ha.enabled')) {
            return 0.0;
        }

        try {
            app(RaftClient::class)->clusterStatus();

            return 1.0;
        } catch (RaftUnavailableException|Throwable) {
            return 0.0;
        }
    }
}
