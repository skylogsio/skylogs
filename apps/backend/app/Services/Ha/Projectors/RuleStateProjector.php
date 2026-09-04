<?php

namespace App\Services\Ha\Projectors;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Services\Ha\AlertStateChange;

/**
 * Sentry and Metabase keep no check document at all: their webhooks write the
 * state straight onto the alert rule, so the rule is the single slot.
 */
final class RuleStateProjector extends AbstractStateProjector
{
    public function projectCheck(BaseModel $check, AlertRule $alertRule): array
    {
        return $this->projectRule($alertRule);
    }

    public function projectDeletion(BaseModel $check, AlertRule $alertRule): array
    {
        return [AlertStateChange::tombstone($this->key($alertRule))];
    }

    public function projectRule(AlertRule $alertRule): array
    {
        $key = $this->key($alertRule);

        return [new AlertStateChange($key, $this->value(
            $alertRule,
            $key,
            [],
            $alertRule->state ?? AlertRule::UNKNOWN,
            $this->changedAt($alertRule),
        ))];
    }
}
