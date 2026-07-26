<?php

namespace App\Services\Ha\Projectors;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Models\PrometheusCheck;
use App\Services\Ha\AlertStateChange;
use App\Services\Ha\AlertStateKey;

final class PrometheusProjector extends ArrayCheckProjector
{
    protected function attribute(): string
    {
        return 'alerts';
    }

    /**
     * Identity is the label set, which is exactly what the evaluator compares
     * when it matches stored alerts against freshly scraped ones.
     */
    protected function instanceId(mixed $entry): string
    {
        return AlertStateKey::prometheusInstanceId(is_array($entry) ? ($entry['labels'] ?? []) : []);
    }

    protected function isFiring(mixed $entry): bool
    {
        $status = is_array($entry) ? ($entry['skylogsStatus'] ?? null) : null;

        return empty($status) || $status == PrometheusCheck::FIRE;
    }

    protected function findCheck(AlertRule $alertRule): ?BaseModel
    {
        return PrometheusCheck::where('alertRuleId', $alertRule->getKey())->first();
    }

    protected function change(AlertRule $alertRule, ?BaseModel $check, string $instanceId, mixed $entry, bool $resolved): AlertStateChange
    {
        $entry = is_array($entry) ? $entry : [];
        $resolved = $resolved || ! $this->isFiring($entry);

        if ($resolved) {
            $entry['skylogsStatus'] = PrometheusCheck::RESOLVED;
        }

        $key = $this->key($alertRule, $instanceId);

        return new AlertStateChange($key, $this->value(
            $alertRule,
            $key,
            ['labels' => $entry['labels'] ?? []],
            $resolved ? AlertRule::RESOlVED : AlertRule::CRITICAL,
            $this->changedAt($check),
            [
                'entry' => $entry,
                'annotations' => $entry['annotations'] ?? [],
                'dataSourceId' => $entry['dataSourceId'] ?? null,
                'checkState' => $check?->state,
            ],
        ));
    }
}
