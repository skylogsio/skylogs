<?php

namespace App\Services\IncidentPolicy;

use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentTimelineEntryType;
use App\Models\AlertRule;
use App\Models\Incident;
use App\Models\IncidentPolicy;
use App\Services\IncidentTimelineService;
use Illuminate\Support\Collection;

class PolicyIncidentCloser
{
    /**
     * @var list<IncidentStatus>
     */
    private const OPEN_STATUSES = [
        IncidentStatus::Open,
        IncidentStatus::Investigating,
    ];

    public function __construct(
        private readonly IncidentTimelineService $timelineService,
        private readonly PolicyIncidentFollowThrough $followThrough,
    ) {}

    /**
     * Resolve policy incidents for this alert when every grouped rule is no longer firing.
     *
     * @return Collection<int, Incident>
     */
    public function clear(AlertRule $clearedAlertRule): Collection
    {
        $alertRuleId = (string) $clearedAlertRule->id;

        $incidents = Incident::query()
            ->where('source', IncidentSource::Policy->value)
            ->whereIn('status', array_map(fn (IncidentStatus $status): string => $status->value, self::OPEN_STATUSES))
            ->where('alertRuleIds', $alertRuleId)
            ->get();

        $resolved = collect();

        foreach ($incidents as $incident) {
            $updated = $this->clearIncident($incident, $alertRuleId);

            if ($updated !== null) {
                $resolved->push($updated);
            }
        }

        return $resolved->values();
    }

    private function clearIncident(Incident $incident, string $clearedAlertRuleId): ?Incident
    {
        $policy = $incident->policyId
            ? IncidentPolicy::query()->where('_id', $incident->policyId)->first()
            : null;

        if ($policy === null || ! ($policy->incident['autoResolveOnAlertClear'] ?? false)) {
            return null;
        }

        if ($this->hasFiringAlert($incident, $clearedAlertRuleId)) {
            return null;
        }

        $now = now();

        $incident->update([
            'status' => IncidentStatus::Resolved,
            'resolvedAt' => $now,
            'resolvedBy' => null,
        ]);

        $this->timelineService->recordSystemEntry(
            $incident,
            IncidentTimelineEntryType::Resolved,
            'Incident auto-resolved because all grouped alerts cleared.',
            null,
            [
                'alertRuleId' => $clearedAlertRuleId,
                'resolvedAt' => $now->toIso8601String(),
            ],
        );

        $resolved = $incident->fresh();

        if ($resolved !== null) {
            $this->followThrough->onResolved($resolved);
        }

        return $resolved;
    }

    private function hasFiringAlert(Incident $incident, string $clearedAlertRuleId): bool
    {
        $ids = array_values(array_filter(
            array_map('strval', $incident->alertRuleIds ?? []),
            fn (string $id): bool => $id !== $clearedAlertRuleId,
        ));

        if ($ids === []) {
            return false;
        }

        return AlertRule::query()
            ->whereIn('_id', $ids)
            ->get()
            ->contains(fn (AlertRule $rule): bool => AlertRule::isFiringState($rule->state));
    }
}
