<?php

namespace App\Services\Ha\Projectors;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Services\Ha\AlertStateKey;
use DateTimeInterface;

abstract class AbstractStateProjector implements StateProjector
{
    protected function key(AlertRule $alertRule, string $instanceId = AlertStateKey::SINGLE_SLOT): AlertStateKey
    {
        return AlertStateKey::make((string) $alertRule->getKey(), $this->ruleType($alertRule), $instanceId);
    }

    protected function ruleType(AlertRule $alertRule): AlertRuleType
    {
        return $alertRule->type instanceof AlertRuleType
            ? $alertRule->type
            : AlertRuleType::from((string) $alertRule->type);
    }

    /**
     * @param  array<string, mixed>  $instance
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function value(
        AlertRule $alertRule,
        AlertStateKey $key,
        array $instance,
        string $state,
        int $changedAt,
        array $extra = [],
    ): array {
        $isResolved = $state === AlertRule::RESOlVED;

        return [
            'alertRuleId' => (string) $alertRule->getKey(),
            'alertRuleName' => $alertRule->name,
            'type' => $key->type,
            'instance' => $instance,
            'state' => $state,
            'firedAt' => $isResolved ? null : $changedAt,
            'resolvedAt' => $isResolved ? $changedAt : null,
            'rule' => $this->ruleAggregate($alertRule),
            'extra' => $extra,
        ];
    }

    /**
     * The leader's authoritative aggregate, so a follower converges even when a
     * single instance delivery is lost.
     *
     * @return array<string, mixed>
     */
    protected function ruleAggregate(AlertRule $alertRule): array
    {
        return [
            'state' => $alertRule->state,
            'fireCount' => (int) ($alertRule->fireCount ?? 0),
            'notifyAt' => $alertRule->notifyAt === null ? null : (int) $alertRule->notifyAt,
            'acknowledgedBy' => $alertRule->acknowledgedBy === null ? null : (string) $alertRule->acknowledgedBy,
        ];
    }

    protected function changedAt(?BaseModel $model): int
    {
        $updatedAt = $model?->updatedAt;

        return $updatedAt instanceof DateTimeInterface ? $updatedAt->getTimestamp() : time();
    }

    /**
     * @param  mixed  $entries
     * @return array<int, array<string, mixed>>
     */
    protected function toEntries($entries): array
    {
        return collect($entries ?? [])
            ->map(fn ($entry): array => is_array($entry) ? $entry : (array) $entry)
            ->values()
            ->all();
    }
}
