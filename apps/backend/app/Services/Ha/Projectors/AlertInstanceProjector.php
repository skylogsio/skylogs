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
     * Firing instances carry the aggregate. When none are left, one existing
     * instance still has to: the resolve that cleared the last fire is published
     * before the rule is refreshed, and without a carrier that refresh never
     * reaches a follower.
     */
    public function projectRule(AlertRule $alertRule): array
    {
        $firing = AlertInstance::where('alertRuleId', $alertRule->getKey())
            ->where('state', AlertInstance::FIRE)
            ->get();

        if ($firing->isNotEmpty()) {
            return $firing
                ->map(fn (AlertInstance $instance): AlertStateChange => $this->change($alertRule, $instance))
                ->all();
        }

        $carrier = $this->carrier($alertRule);

        return $carrier ? [$this->change($alertRule, $carrier)] : [];
    }

    /**
     * A resolved row is the one the follower should rewrite. A notification row
     * is only used when the rule has nothing else left to carry the aggregate.
     */
    private function carrier(AlertRule $alertRule): ?AlertInstance
    {
        $resolved = AlertInstance::where('alertRuleId', $alertRule->getKey())
            ->where('state', AlertInstance::RESOLVED)
            ->orderByDesc('updatedAt')
            ->first();

        if ($resolved) {
            return $resolved;
        }

        return AlertInstance::where('alertRuleId', $alertRule->getKey())
            ->orderByDesc('updatedAt')
            ->first();
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
