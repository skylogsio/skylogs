<?php

namespace App\Services\AlertStatus\Contracts;

use App\Models\AlertRule;
use App\Services\AlertStatus\AlertStatusEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface AlertStatusEventSource
{
    /**
     * Fetch every status-changing event for the given alert rules that occurred within the window.
     *
     * @param  Collection<string, AlertRule>  $alertRules  keyed by alert rule id, all of this source's type
     * @return Collection<int, AlertStatusEvent>
     */
    public function fetchEvents(Collection $alertRules, Carbon $from, Carbon $to): Collection;

    /**
     * Fetch, per alert rule, the single most recent event that occurred strictly before the window,
     * establishing which status was active at the start of the window.
     *
     * @param  Collection<string, AlertRule>  $alertRules  keyed by alert rule id, all of this source's type
     * @return Collection<string, AlertStatusEvent> keyed by alert rule id
     */
    public function fetchBaseline(Collection $alertRules, Carbon $before): Collection;
}
