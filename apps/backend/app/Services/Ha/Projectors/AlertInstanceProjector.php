<?php

namespace App\Services\Ha\Projectors;

use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Services\Ha\AlertStateChange;
use App\Services\Ha\AlertStateKey;

/**
 * API and notification rules, whose instances are documents of their own
 * rather than entries inside a check document.
 */
final class AlertInstanceProjector extends AbstractStateProjector
{
    public function projectCheck(BaseModel $check, AlertRule $alertRule): array
    {
        return [$this->change($alertRule, $check)];
    }

    public function projectDeletion(BaseModel $check, AlertRule $alertRule): array
    {
        return [AlertStateChange::tombstone(
            $this->key($alertRule, AlertStateKey::apiInstanceId($check->instance))
        )];
    }

    /**
     * Only the firing instances carry the aggregate; a resolved instance was
     * already replicated by its own write and does not need refreshing.
     */
    public function projectRule(AlertRule $alertRule): array
    {
        return AlertInstance::where('alertRuleId', $alertRule->getKey())
            ->where('state', AlertInstance::FIRE)
            ->get()
            ->map(fn (AlertInstance $instance): AlertStateChange => $this->change($alertRule, $instance))
            ->all();
    }

    private function change(AlertRule $alertRule, BaseModel $instance): AlertStateChange
    {
        $key = $this->key($alertRule, AlertStateKey::apiInstanceId($instance->instance));

        return new AlertStateChange($key, $this->value(
            $alertRule,
            $key,
            ['instance' => $instance->instance],
            $this->stateFor($instance),
            $this->changedAt($instance),
            [
                'instanceState' => $instance->state,
                'description' => $instance->description,
                'summary' => $instance->summary,
                'job' => $instance->job,
            ],
        ));
    }

    private function stateFor(BaseModel $instance): string
    {
        return match ($instance->state) {
            AlertInstance::FIRE => AlertRule::CRITICAL,
            AlertInstance::RESOLVED => AlertRule::RESOlVED,
            default => AlertRule::UNKNOWN,
        };
    }
}
