<?php

namespace App\Services\Ha\Projectors;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Models\ZabbixCheck;
use App\Services\Ha\AlertStateChange;
use App\Services\Ha\AlertStateKey;
use App\Services\Ha\HaStateVersionStore;

final class ZabbixProjector extends ArrayCheckProjector
{
    public function __construct(private readonly HaStateVersionStore $versions) {}

    protected function attribute(): string
    {
        return 'fireEvents';
    }

    protected function instanceId(mixed $entry): string
    {
        return AlertStateKey::zabbixInstanceId(is_scalar($entry) ? $entry : '');
    }

    /**
     * The array holds nothing but the ids of events that are currently firing.
     */
    protected function isFiring(mixed $entry): bool
    {
        return true;
    }

    protected function findCheck(AlertRule $alertRule): ?BaseModel
    {
        return ZabbixCheck::where('alertRuleId', $alertRule->getKey())->first();
    }

    /**
     * Zabbix pushes and pulls event ids with atomic array operators, which do
     * not fire model events, so the rule save is the only signal that an event
     * was cleared. Anything the log still believes to be firing but that is no
     * longer in the array is published as resolved.
     */
    public function projectRule(AlertRule $alertRule): array
    {
        $check = $this->findCheck($alertRule);
        $current = $check ? $this->slots($check->getAttribute($this->attribute())) : [];

        $changes = [];
        $firingKeys = [];

        foreach ($current as $instanceId => $entry) {
            $change = $this->change($alertRule, $check, (string) $instanceId, $entry, false);
            $firingKeys[] = $change->key->toString();
            $changes[] = $change;
        }

        $prefix = AlertStateKey::prefixFor((string) $alertRule->getKey());

        foreach ($this->versions->unresolvedKeysWithPrefix($prefix) as $key) {
            if (in_array($key, $firingKeys, true)) {
                continue;
            }

            $instanceId = AlertStateKey::parse($key)->instanceId;
            $changes[] = $this->change($alertRule, $check, $instanceId, $instanceId, true);
        }

        return $changes;
    }

    protected function change(AlertRule $alertRule, ?BaseModel $check, string $instanceId, mixed $entry, bool $resolved): AlertStateChange
    {
        $key = $this->key($alertRule, $instanceId);

        return new AlertStateChange($key, $this->value(
            $alertRule,
            $key,
            ['eventId' => $instanceId],
            $resolved ? AlertRule::RESOlVED : AlertRule::CRITICAL,
            $this->changedAt($check),
            [
                'checkState' => $check?->state,
                'dataSourceId' => $check?->dataSourceId,
            ],
        ));
    }
}
