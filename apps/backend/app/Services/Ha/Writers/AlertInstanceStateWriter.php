<?php

namespace App\Services\Ha\Writers;

use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Services\Ha\AlertStateKey;
use App\Services\Ha\AlertStateValue;

/**
 * API and notification rules, whose instances are documents rather than entries
 * inside a check document.
 */
final class AlertInstanceStateWriter implements StateWriter
{
    public function localState(AlertRule $alertRule, AlertStateValue $value): string
    {
        $instance = $this->findInstance($alertRule, $this->instanceName($value));

        return match ($instance?->state) {
            AlertInstance::FIRE => AlertRule::CRITICAL,
            AlertInstance::RESOLVED => AlertRule::RESOlVED,
            null => AlertRule::RESOlVED,
            default => AlertRule::UNKNOWN,
        };
    }

    public function write(AlertRule $alertRule, AlertStateValue $value): void
    {
        $instanceName = $this->instanceName($value);

        $instance = $this->findInstance($alertRule, $instanceName) ?? new AlertInstance([
            'alertRuleId' => $alertRule->getKey(),
            'instance' => $instanceName,
        ]);

        $instance->alertRuleId = $alertRule->getKey();
        $instance->alertRuleName = $value->alertRuleName ?? $alertRule->name;
        $instance->instance = $instanceName;
        $instance->state = $this->instanceState($value);
        $instance->description = $value->extra('description');
        $instance->summary = $value->extra('summary');
        $instance->job = $value->extra('job');
        $instance->save();
    }

    /**
     * The API timeline reads api_alert_status_histories, which the instance
     * builds from the rule's other instances, so the row has to be written
     * after the instance itself has been stored.
     */
    public function writeHistory(AlertRule $alertRule, AlertStateValue $value): void
    {
        $instance = $this->findInstance($alertRule, $this->instanceName($value));

        if (! $instance) {
            return;
        }

        $instance->createStatusHistory($instance->createHistory());
    }

    public function remove(AlertRule $alertRule, AlertStateKey $key): void
    {
        AlertInstance::where('alertRuleId', $alertRule->getKey())
            ->get()
            ->filter(fn (AlertInstance $instance): bool => AlertStateKey::apiInstanceId($instance->instance) === $key->instanceId)
            ->each(fn (AlertInstance $instance) => $instance->delete());
    }

    private function findInstance(AlertRule $alertRule, ?string $instanceName): ?AlertInstance
    {
        return AlertInstance::where('alertRuleId', $alertRule->getKey())
            ->where('instance', $instanceName)
            ->first();
    }

    private function instanceName(AlertStateValue $value): ?string
    {
        $instance = $value->instance['instance'] ?? null;

        return $instance === null ? null : (string) $instance;
    }

    /**
     * The leader sends the instance's own state code, which distinguishes a
     * notification from a fire; the replicated state alone cannot.
     */
    private function instanceState(AlertStateValue $value): int
    {
        $state = $value->extra('instanceState');

        if ($state !== null) {
            return (int) $state;
        }

        return $value->isFiring() ? AlertInstance::FIRE : AlertInstance::RESOLVED;
    }
}
