<?php

namespace App\Services\IncidentPolicy;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentTimelineEntryType;
use App\Models\Incident;
use App\Models\IncidentPolicy;
use App\Services\IncidentTimelineService;
use Illuminate\Support\Collection;

class PolicyIncidentOpener
{
    public function __construct(
        private readonly IncidentPolicyMatcher $matcher,
        private readonly IncidentTimelineService $timelineService,
    ) {}

    /**
     * Open one incident per matching auto-create policy. Grouping is not applied yet.
     *
     * @return Collection<int, Incident>
     */
    public function open(AlertMatchContext $context): Collection
    {
        return $this->matcher
            ->matching($context)
            ->map(fn (IncidentPolicy $policy): Incident => $this->openForPolicy($policy, $context))
            ->values();
    }

    public function openForPolicy(IncidentPolicy $policy, AlertMatchContext $context): Incident
    {
        $severity = $this->severity($policy, $context);
        $now = now();

        $incident = Incident::create([
            'title' => $this->title($policy, $context, $severity),
            'description' => '',
            'teamIds' => array_values(array_map('strval', $policy->teamIds ?? [])),
            'tags' => AlertMatchContext::stringList($context->tags),
            'startedAt' => $now,
            'detectedAt' => $now,
            'resolvedAt' => null,
            'resolvedBy' => null,
            'alertRuleIds' => $this->alertRuleIds($context),
            'severity' => $severity,
            'status' => IncidentStatus::Open,
            'source' => IncidentSource::Policy,
            'policyId' => (string) $policy->id,
            'createdBy' => null,
            'acknowledgements' => [],
        ]);

        $this->timelineService->recordSystemEntry(
            $incident,
            IncidentTimelineEntryType::Created,
            'Incident opened from policy '.$policy->name.' as '.$severity->value.'.',
            null,
            [
                'severity' => $severity->value,
                'status' => IncidentStatus::Open->value,
                'policyId' => (string) $policy->id,
            ],
        );

        return $incident;
    }

    private function severity(IncidentPolicy $policy, AlertMatchContext $context): IncidentSeverity
    {
        $incident = $policy->incident ?? [];
        $map = $incident['severityMap'] ?? [];
        $state = strtolower((string) ($context->alertState ?? ''));
        $mapped = $state === '' ? null : ($map[$state] ?? $map[$context->alertState] ?? null);

        return IncidentSeverity::tryFrom((string) $mapped)
            ?? IncidentSeverity::tryFrom((string) ($incident['defaultSeverity'] ?? ''))
            ?? IncidentSeverity::Sev3;
    }

    private function title(IncidentPolicy $policy, AlertMatchContext $context, IncidentSeverity $severity): string
    {
        $name = $context->alertName ?: 'Alert';
        $template = $policy->incident['titleTemplate'] ?? null;

        if (is_string($template) && $template !== '') {
            return strtr($template, [
                '{{name}}' => $name,
                '{{severity}}' => $severity->value,
                '{{policy}}' => $policy->name,
            ]);
        }

        return $name;
    }

    /**
     * @return list<string>
     */
    private function alertRuleIds(AlertMatchContext $context): array
    {
        return $context->alertRuleId === null || $context->alertRuleId === ''
            ? []
            : [(string) $context->alertRuleId];
    }
}
