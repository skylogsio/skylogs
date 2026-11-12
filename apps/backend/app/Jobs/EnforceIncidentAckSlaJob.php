<?php

namespace App\Jobs;

use App\Services\IncidentPolicy\PolicyIncidentFollowThrough;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class EnforceIncidentAckSlaJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $incidentId)
    {
        $this->onQueue('sendNotifies');
    }

    public function handle(PolicyIncidentFollowThrough $followThrough): void
    {
        $followThrough->enforceAckSla($this->incidentId);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
