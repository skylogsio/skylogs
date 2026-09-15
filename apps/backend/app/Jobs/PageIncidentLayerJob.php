<?php

namespace App\Jobs;

use App\Services\IncidentPolicy\PolicyIncidentPager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PageIncidentLayerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $incidentId,
        public readonly string $policyId,
        public readonly string $teamId,
        public readonly int $layerLevel,
    ) {
        $this->onQueue('sendNotifies');
    }

    public function handle(PolicyIncidentPager $pager): void
    {
        $pager->pageLayer($this->incidentId, $this->policyId, $this->teamId, $this->layerLevel);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
