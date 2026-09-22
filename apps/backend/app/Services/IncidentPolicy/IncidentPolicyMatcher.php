<?php

namespace App\Services\IncidentPolicy;

use App\Models\IncidentPolicy;
use Illuminate\Support\Collection;

class IncidentPolicyMatcher
{
    /**
     * Enabled auto-create policies that match the alert on any listed filter.
     *
     * @return Collection<int, IncidentPolicy>
     */
    public function matching(AlertMatchContext $context): Collection
    {
        return IncidentPolicy::query()
            ->where('enabled', true)
            ->get()
            ->filter(fn (IncidentPolicy $policy): bool => $this->autoCreates($policy) && $this->matches($policy, $context))
            ->values();
    }

    public function matches(IncidentPolicy $policy, AlertMatchContext $context): bool
    {
        $match = $policy->match ?? [];

        return $this->listsIntersect($match['alertRuleIds'] ?? [], $this->optionalList($context->alertRuleId))
            || $this->listsIntersect($match['tags'] ?? [], $context->tags)
            || $this->listsIntersect($match['serviceIds'] ?? [], $context->serviceIds)
            || $this->listsIntersect($match['dataSourceTypes'] ?? [], $this->optionalList($context->dataSourceType));
    }

    public function autoCreates(IncidentPolicy $policy): bool
    {
        return (bool) ($policy->incident['autoCreate'] ?? true);
    }

    /**
     * @param  list<mixed>  $policyValues
     * @param  list<mixed>  $alertValues
     */
    private function listsIntersect(array $policyValues, array $alertValues): bool
    {
        $policyValues = AlertMatchContext::stringList($policyValues);
        $alertValues = AlertMatchContext::stringList($alertValues);

        if ($policyValues === [] || $alertValues === []) {
            return false;
        }

        return array_intersect($policyValues, $alertValues) !== [];
    }

    /**
     * @return list<string>
     */
    private function optionalList(?string $value): array
    {
        return $value === null || $value === '' ? [] : [$value];
    }
}
