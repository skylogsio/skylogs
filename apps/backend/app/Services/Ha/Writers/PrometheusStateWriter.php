<?php

namespace App\Services\Ha\Writers;

use App\Models\AlertRule;
use App\Models\PrometheusCheck;
use App\Services\Ha\AlertStateKey;
use App\Services\Ha\AlertStateValue;

final class PrometheusStateWriter extends ArrayCheckStateWriter
{
    protected function checkModel(): string
    {
        return PrometheusCheck::class;
    }

    protected function attribute(): string
    {
        return 'alerts';
    }

    protected function instanceId(mixed $entry): string
    {
        return AlertStateKey::prometheusInstanceId(is_array($entry) ? ($entry['labels'] ?? []) : []);
    }

    protected function stateForEntry(mixed $entry): string
    {
        $status = is_array($entry) ? ($entry['skylogsStatus'] ?? null) : null;

        return empty($status) || $status == PrometheusCheck::FIRE ? AlertRule::CRITICAL : AlertRule::RESOlVED;
    }

    /**
     * A resolved alert stays in the array carrying skylogsStatus RESOLVED until
     * the check is pruned; that is what the evaluator does locally, and the
     * history row counts both sides.
     */
    protected function keepsResolvedEntries(): bool
    {
        return true;
    }

    protected function entryFor(AlertStateValue $value): mixed
    {
        $entry = $value->extraArray('entry');

        if ($entry === []) {
            $entry = [
                'labels' => $value->instance['labels'] ?? [],
                'annotations' => $value->extraArray('annotations'),
                'dataSourceId' => $value->extra('dataSourceId'),
                'alertRuleName' => $value->alertRuleName,
            ];
        }

        $entry['skylogsStatus'] = $value->isFiring() ? PrometheusCheck::FIRE : PrometheusCheck::RESOLVED;

        return $entry;
    }

    /**
     * @param  array<int, mixed>  $entries
     */
    protected function checkState(array $entries, AlertStateValue $value): mixed
    {
        $firing = collect($entries)->contains(
            fn (mixed $entry): bool => $this->stateForEntry($entry) !== AlertRule::RESOlVED,
        );

        return $firing ? PrometheusCheck::FIRE : PrometheusCheck::RESOLVED;
    }

    public function writeHistory(AlertRule $alertRule, AlertStateValue $value): void
    {
        $check = $this->findCheck($alertRule);

        $check?->createHistory();
    }
}
