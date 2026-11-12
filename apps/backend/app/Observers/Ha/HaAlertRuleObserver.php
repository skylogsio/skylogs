<?php

namespace App\Observers\Ha;

use App\Models\AlertRule;
use App\Services\Ha\AlertStateReplicator;

/**
 * Replicates the alert rule aggregate. Only the runtime fields matter here;
 * the rule's configuration travels with the config snapshot instead.
 */
class HaAlertRuleObserver
{
    public function __construct(private readonly AlertStateReplicator $replicator) {}

    public function saved(AlertRule $alertRule): void
    {
        $this->replicator->replicateRule($alertRule);
    }

    public function deleted(AlertRule $alertRule): void
    {
        $this->replicator->replicateRuleDeletion($alertRule);
    }

    public function forceDeleted(AlertRule $alertRule): void
    {
        $this->replicator->replicateRuleDeletion($alertRule);
    }
}
