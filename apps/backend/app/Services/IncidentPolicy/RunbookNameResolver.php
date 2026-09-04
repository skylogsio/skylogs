<?php

namespace App\Services\IncidentPolicy;

use App\Models\Runbook;

/**
 * Best-effort mapping of policy runbook references onto runbook ids.
 *
 * A policy may name a runbook that has not been written yet, so an unresolvable
 * reference is not an error here: the name stays in `runbookNames` and only the
 * references that currently exist are mirrored into `runbookIds`.
 */
class RunbookNameResolver
{
    /**
     * @param  list<string>  $references  Runbook names, or 24 character ids
     * @return list<string>
     */
    public function idsFor(array $references): array
    {
        if ($references === []) {
            return [];
        }

        $ids = array_values(array_filter($references, $this->isObjectId(...)));
        $names = array_values(array_filter($references, fn (string $value) => ! $this->isObjectId($value)));

        $runbooks = Runbook::query()
            ->where(function ($query) use ($ids, $names) {
                if ($ids !== []) {
                    $query->whereIn('_id', $ids);
                }

                if ($names !== []) {
                    $query->orWhereIn('name', $names);
                }
            })
            ->get();

        $byId = [];
        $byName = [];

        foreach ($runbooks as $runbook) {
            $byId[(string) $runbook->id] = (string) $runbook->id;
            $byName[(string) $runbook->name] = (string) $runbook->id;
        }

        $resolved = [];

        foreach ($references as $reference) {
            $id = $this->isObjectId($reference)
                ? ($byId[$reference] ?? null)
                : ($byName[$reference] ?? null);

            if ($id !== null) {
                $resolved[] = $id;
            }
        }

        return array_values(array_unique($resolved));
    }

    private function isObjectId(string $value): bool
    {
        return preg_match('/^[0-9a-fA-F]{24}$/', $value) === 1;
    }
}
