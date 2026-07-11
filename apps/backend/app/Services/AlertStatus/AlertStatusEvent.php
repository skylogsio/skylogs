<?php

namespace App\Services\AlertStatus;

use Carbon\Carbon;

/**
 * A single normalized status change for one alert rule, sourced from whichever
 * type-specific history/webhook collection that alert rule type writes to.
 */
final class AlertStatusEvent
{
    public function __construct(
        public readonly string $alertRuleId,
        public readonly Carbon $occurredAt,
        public readonly string $status,
        public readonly int $count,
        public readonly ?string $summary,
    ) {}
}
