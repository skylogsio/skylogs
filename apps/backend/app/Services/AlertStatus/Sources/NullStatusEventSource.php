<?php

namespace App\Services\AlertStatus\Sources;

use App\Services\AlertStatus\Contracts\AlertStatusEventSource;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Used for alert rule types that don't currently persist any usable status
 * history (Metabase, Splunk, Notification). Their timeline will report
 * "unknown" for the entire window until real history storage is added for them.
 */
final class NullStatusEventSource implements AlertStatusEventSource
{
    public function fetchEvents(Collection $alertRules, Carbon $from, Carbon $to): Collection
    {
        return collect();
    }

    public function fetchBaseline(Collection $alertRules, Carbon $before): Collection
    {
        return collect();
    }
}
