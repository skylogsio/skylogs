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
use Throwable;

class PolicyIncidentOpener
{
    /**
     * @var list<IncidentStatus>
     */
    private const GROUPABLE_STATUSES = [
        IncidentStatus::Open,
        IncidentStatus::Investigating,
    ];

    public function __construct(
        private readonly IncidentPolicyMatcher $matcher,
        private readonly IncidentGroupingKey $groupingKey,
        private readonly IncidentTimelineService $timelineService,
        private readonly PolicyIncidentPager $pager,
        private readonly PolicyIncidentFollowThrough $followThrough,
    ) {}

    /**
     * Open or join one incident per matching auto-create policy.
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
        $fingerprint = $this->groupingKey->fingerprint($policy, $context);
        $existing = $this->existingInWindow($policy, $fingerprint);

        if ($existing !== null) {
            return $this->join($existing, $policy, $context);
        }

        return $this->create($policy, $context, $fingerprint);
    }

    private function existingInWindow(IncidentPolicy $policy, string $fingerprint): ?Incident
    {
        $windowStart = now()->subMinutes($this->groupingKey->windowMinutes($policy));

        return Incident::query()
            ->where('policyId', (string) $policy->id)
            ->where('groupingKey', $fingerprint)
            ->where('source', IncidentSource::Policy->value)
            ->whereIn('status', array_map(fn (IncidentStatus $status): string => $status->value, self::GROUPABLE_STATUSES))
            ->where('detectedAt', '>=', $windowStart)
            ->orderByDesc('detectedAt')
            ->first();
    }

    private function create(IncidentPolicy $policy, AlertMatchContext $context, string $fingerprint): Incident
    {
        $severity = $this->severity($policy, $context);
        $now = now();
        $sla = $this->followThrough->snapshot($policy, $severity);
        $commanderId = ($sla['requireCommander'] ?? false)
            ? $this->followThrough->commanderUserId($policy)
            : null;

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
            'groupingKey' => $fingerprint,
            'createdBy' => null,
            'commanderId' => $commanderId,
            'acknowledgements' => [],
            'policySla' => $sla,
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
                'groupingKey' => $fingerprint,
            ],
        );

        try {
            $this->pager->page($incident, $policy, $context);
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            $this->followThrough->schedule($incident);

            if ($commanderId !== null) {
                $this->followThrough->recordCommander(
                    $incident,
                    $commanderId,
                    'Commander set from on-call.',
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $incident;
    }

    private function join(Incident $incident, IncidentPolicy $policy, AlertMatchContext $context): Incident
    {
        $severity = $this->severity($policy, $context);
        $alertRuleIds = array_values(array_unique([
            ...array_map('strval', $incident->alertRuleIds ?? []),
            ...$this->alertRuleIds($context),
        ]));
        $tags = AlertMatchContext::stringList([
            ...($incident->tags ?? []),
            ...$context->tags,
        ]);

        $updates = [
            'detectedAt' => now(),
            'alertRuleIds' => $alertRuleIds,
            'tags' => $tags,
        ];

        if ($this->isMoreSevere($severity, $incident->severity)) {
            $updates['severity'] = $severity;
        }

        $incident->update($updates);

        $this->timelineService->recordSystemEntry(
            $incident,
            IncidentTimelineEntryType::Detection,
            'Alert '.($context->alertName ?: 'Alert').' grouped into this incident.',
            null,
            [
                'alertRuleId' => $context->alertRuleId,
                'severity' => $severity->value,
            ],
        );

        return $incident->fresh();
    }

    private function isMoreSevere(IncidentSeverity $candidate, IncidentSeverity $current): bool
    {
        return $this->severityRank($candidate) < $this->severityRank($current);
    }

    private function severityRank(IncidentSeverity $severity): int
    {
        return match ($severity) {
            IncidentSeverity::Sev1 => 1,
            IncidentSeverity::Sev2 => 2,
            IncidentSeverity::Sev3 => 3,
            IncidentSeverity::Sev4 => 4,
        };
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
