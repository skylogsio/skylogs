<?php

namespace App\Observers\Ha;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Services\Ha\AlertStateReplicator;

/**
 * Replicates every write to a check document, whichever service made it.
 *
 * Registered for PrometheusCheck, GrafanaCheck, ZabbixCheck, AlertInstance,
 * ElasticCheck, VictoriaLogsCheck and HealthCheck; the per type differences all
 * live in the projectors, so one observer covers them all.
 */
class HaCheckObserver
{
    public function __construct(private readonly AlertStateReplicator $replicator) {}

    public function saved(BaseModel $check): void
    {
        $alertRule = $this->alertRule($check);

        if (! $alertRule) {
            return;
        }

        $this->replicator->replicateCheck($check, $alertRule);
    }

    public function deleted(BaseModel $check): void
    {
        $alertRule = $this->alertRule($check);

        if (! $alertRule) {
            return;
        }

        $this->replicator->replicateCheckDeletion($check, $alertRule);
    }

    /**
     * Prefers the already loaded relation: the evaluation services mutate the
     * rule in memory before saving the check, and that in flight aggregate is
     * the one the follower should receive.
     */
    private function alertRule(BaseModel $check): ?AlertRule
    {
        if (! $this->replicator->shouldReplicate() || empty($check->alertRuleId)) {
            return null;
        }

        if ($check->relationLoaded('alertRule')) {
            $loaded = $check->getRelation('alertRule');

            if ($loaded instanceof AlertRule) {
                return $loaded;
            }
        }

        return AlertRule::find($check->alertRuleId);
    }
}
