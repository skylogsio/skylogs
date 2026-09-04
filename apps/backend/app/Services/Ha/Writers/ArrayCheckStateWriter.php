<?php

namespace App\Services\Ha\Writers;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Services\Ha\AlertStateKey;
use App\Services\Ha\AlertStateValue;

/**
 * Shared handling for the check documents that keep their instances in an array
 * attribute, where one replicated slot is one entry of that array.
 */
abstract class ArrayCheckStateWriter implements StateWriter
{
    /**
     * @return class-string<BaseModel>
     */
    abstract protected function checkModel(): string;

    abstract protected function attribute(): string;

    abstract protected function instanceId(mixed $entry): string;

    /**
     * The entry to store for this slot, built from what the leader sent. An
     * array for the types whose entries are documents, a scalar for Zabbix,
     * whose array holds nothing but event ids.
     */
    abstract protected function entryFor(AlertStateValue $value): mixed;

    abstract protected function stateForEntry(mixed $entry): string;

    /**
     * Whether a resolved slot keeps its entry in the array, as Prometheus does,
     * or leaves it, as Grafana and Zabbix do.
     */
    abstract protected function keepsResolvedEntries(): bool;

    /**
     * The value of the check document's own state attribute, given what is left
     * in the array after the write. Null leaves the attribute untouched, for
     * the check documents that do not keep one.
     *
     * @param  array<int, mixed>  $entries
     */
    abstract protected function checkState(array $entries, AlertStateValue $value): mixed;

    public function localState(AlertRule $alertRule, AlertStateValue $value): string
    {
        $entry = $this->findEntry($this->entries($this->findCheck($alertRule)), $value->key->instanceId);

        return $entry === null ? AlertRule::RESOlVED : $this->stateForEntry($entry);
    }

    public function write(AlertRule $alertRule, AlertStateValue $value): void
    {
        $check = $this->checkFor($alertRule);
        $entries = $this->entries($check);
        $instanceId = $value->key->instanceId;

        $entries = array_values(array_filter(
            $entries,
            fn (mixed $entry): bool => $this->instanceId($entry) !== $instanceId,
        ));

        if ($value->isFiring() || $this->keepsResolvedEntries()) {
            $entries[] = $this->entryFor($value);
        }

        $checkState = $this->checkState($entries, $value);

        $check->setAttribute($this->attribute(), $entries);

        if ($checkState !== null) {
            $check->setAttribute('state', $checkState);
        }

        $check->save();
    }

    public function remove(AlertRule $alertRule, AlertStateKey $key): void
    {
        $check = $this->findCheck($alertRule);

        if (! $check) {
            return;
        }

        $entries = array_values(array_filter(
            $this->entries($check),
            fn (mixed $entry): bool => $this->instanceId($entry) !== $key->instanceId,
        ));

        $check->setAttribute($this->attribute(), $entries);
        $check->save();
    }

    protected function findCheck(AlertRule $alertRule): ?BaseModel
    {
        $model = $this->checkModel();

        return $model::where('alertRuleId', $alertRule->getKey())->first();
    }

    protected function checkFor(AlertRule $alertRule): BaseModel
    {
        $model = $this->checkModel();

        return $this->findCheck($alertRule) ?? new $model([
            'alertRuleId' => $alertRule->getKey(),
            $this->attribute() => [],
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected function entries(?BaseModel $check): array
    {
        if (! $check) {
            return [];
        }

        return collect($check->getAttribute($this->attribute()) ?? [])
            ->map(fn (mixed $entry): mixed => is_object($entry) ? (array) $entry : $entry)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $entries
     */
    protected function findEntry(array $entries, string $instanceId): mixed
    {
        foreach ($entries as $entry) {
            if ($this->instanceId($entry) === $instanceId) {
                return $entry;
            }
        }

        return null;
    }
}
