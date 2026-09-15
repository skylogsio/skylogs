<?php

namespace App\Services\IncidentPolicy;

use App\Models\IncidentPolicy;

class IncidentGroupingKey
{
    /**
     * Stable fingerprint for one policy + the alert's grouping dimensions.
     * An empty grouping key means every fire for that policy shares one group.
     */
    public function fingerprint(IncidentPolicy $policy, AlertMatchContext $context): string
    {
        $keys = AlertMatchContext::stringList($policy->grouping['key'] ?? []);
        sort($keys);

        $parts = ['policyId' => (string) $policy->id];

        foreach ($keys as $key) {
            $parts[$key] = $this->value($key, $context);
        }

        return hash('sha256', json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function windowMinutes(IncidentPolicy $policy): int
    {
        $window = (int) ($policy->grouping['windowMinutes'] ?? IncidentPolicyDslParser::DEFAULT_GROUPING_WINDOW_MINUTES);

        return max(1, $window);
    }

    private function value(string $key, AlertMatchContext $context): string
    {
        return match ($key) {
            'alertRuleId' => (string) ($context->alertRuleId ?? ''),
            'dataSourceType' => (string) ($context->dataSourceType ?? ''),
            'serviceId' => implode(',', AlertMatchContext::stringList($context->serviceIds)),
            'tag' => implode(',', AlertMatchContext::stringList($context->tags)),
            default => '',
        };
    }
}
