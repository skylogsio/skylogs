<?php

namespace App\Services\Ha\Projectors;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Services\Ha\AlertStateChange;

/**
 * Shared diffing for the check documents that hold their instances in an array
 * attribute. One change per changed instance, so a rule with fifty firing
 * alerts does not republish all fifty every time one of them moves.
 */
abstract class ArrayCheckProjector extends AbstractStateProjector
{
    /**
     * Name of the array attribute holding the instances.
     */
    abstract protected function attribute(): string;

    abstract protected function instanceId(mixed $entry): string;

    abstract protected function isFiring(mixed $entry): bool;

    abstract protected function change(AlertRule $alertRule, ?BaseModel $check, string $instanceId, mixed $entry, bool $resolved): AlertStateChange;

    abstract protected function findCheck(AlertRule $alertRule): ?BaseModel;

    public function projectCheck(BaseModel $check, AlertRule $alertRule): array
    {
        $original = $this->slots($check->getOriginal($this->attribute()));
        $current = $this->slots($check->getAttribute($this->attribute()));

        $changes = [];

        foreach ($current as $instanceId => $entry) {
            if (array_key_exists($instanceId, $original) && $original[$instanceId] == $entry) {
                continue;
            }

            $changes[] = $this->change($alertRule, $check, (string) $instanceId, $entry, false);
        }

        foreach ($original as $instanceId => $entry) {
            if (array_key_exists($instanceId, $current)) {
                continue;
            }

            $changes[] = $this->change($alertRule, $check, (string) $instanceId, $entry, true);
        }

        if ($changes === [] && $check->wasChanged('state')) {
            foreach ($current as $instanceId => $entry) {
                $changes[] = $this->change($alertRule, $check, (string) $instanceId, $entry, ! $this->isFiring($entry));
            }
        }

        return $changes;
    }

    public function projectDeletion(BaseModel $check, AlertRule $alertRule): array
    {
        $changes = [];

        foreach ($this->slots($check->getAttribute($this->attribute())) as $instanceId => $entry) {
            $changes[] = AlertStateChange::tombstone($this->key($alertRule, (string) $instanceId));
        }

        return $changes;
    }

    public function projectRule(AlertRule $alertRule): array
    {
        $check = $this->findCheck($alertRule);

        if (! $check) {
            return [];
        }

        $changes = [];

        foreach ($this->slots($check->getAttribute($this->attribute())) as $instanceId => $entry) {
            if (! $this->isFiring($entry)) {
                continue;
            }

            $changes[] = $this->change($alertRule, $check, (string) $instanceId, $entry, false);
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function slots(mixed $entries): array
    {
        $slots = [];

        foreach ((array) ($entries ?? []) as $entry) {
            $entry = is_object($entry) ? (array) $entry : $entry;
            $slots[$this->instanceId($entry)] = $entry;
        }

        return $slots;
    }
}
