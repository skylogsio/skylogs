<?php

namespace App\Services\Ha\Projectors;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Models\GrafanaCheck;
use App\Models\GrafanaWebhookAlert;
use App\Services\Ha\AlertStateChange;
use App\Services\Ha\AlertStateKey;

/**
 * Serves both Grafana and PMM rules; the key's type segment keeps the two
 * apart so identical fingerprints from the two sources cannot collide.
 */
final class GrafanaProjector extends ArrayCheckProjector
{
    protected function attribute(): string
    {
        return 'alerts';
    }

    protected function instanceId(mixed $entry): string
    {
        return AlertStateKey::grafanaInstanceId(is_array($entry) ? $entry : []);
    }

    /**
     * A resolved alert is dropped from the stored batch, so anything still in
     * the array is firing unless it says otherwise.
     */
    protected function isFiring(mixed $entry): bool
    {
        $status = is_array($entry) ? ($entry['status'] ?? null) : null;

        return $status !== GrafanaWebhookAlert::RESOLVED;
    }

    protected function findCheck(AlertRule $alertRule): ?BaseModel
    {
        return GrafanaCheck::where('alertRuleId', $alertRule->getKey())->first();
    }

    protected function change(AlertRule $alertRule, ?BaseModel $check, string $instanceId, mixed $entry, bool $resolved): AlertStateChange
    {
        $entry = is_array($entry) ? $entry : [];
        $resolved = $resolved || ! $this->isFiring($entry);

        if ($resolved) {
            $entry['status'] = GrafanaWebhookAlert::RESOLVED;
        }

        $entry['instanceKey'] = $instanceId;

        $key = $this->key($alertRule, $instanceId);

        return new AlertStateChange($key, $this->value(
            $alertRule,
            $key,
            [
                'instanceKey' => $instanceId,
                'labels' => $entry['labels'] ?? [],
            ],
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
