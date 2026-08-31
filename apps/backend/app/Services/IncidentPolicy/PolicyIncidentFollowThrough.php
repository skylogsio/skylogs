<?php

namespace App\Services\IncidentPolicy;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentTimelineEntrySource;
use App\Enums\IncidentTimelineEntryType;
use App\Jobs\EnforceIncidentAckSlaJob;
use App\Jobs\EnforceIncidentResolveSlaJob;
use App\Jobs\RemindIncidentStakeholderUpdateJob;
use App\Models\Incident;
use App\Models\IncidentPolicy;
use App\Models\IncidentTimelineEntry;
use App\Models\OnCallPlan;
use App\Models\User;
use App\Services\IncidentTimelineService;
use App\Services\OnCallResolver;
use App\Services\PostMortemService;
use Illuminate\Validation\ValidationException;

class PolicyIncidentFollowThrough
{
    /**
     * @var list<IncidentStatus>
     */
    private const OPEN_STATUSES = [
        IncidentStatus::Open,
        IncidentStatus::Investigating,
    ];

    public function __construct(
        private readonly OnCallResolver $onCallResolver,
        private readonly IncidentTimelineService $timelineService,
        private readonly PolicyIncidentPager $pager,
        private readonly PostMortemService $postMortemService,
    ) {}

    /**
     * Frozen copy of the SEV rule, so later policy edits do not rewrite an in-flight SLA.
     *
     * @return array{
     *     ackWithinMinutes: int|null,
     *     resolveWithinMinutes: int|null,
     *     requireCommander: bool,
     *     stakeholderUpdateEveryMinutes: int|null,
     *     statusPageUpdateRequired: bool,
     *     postmortemRequired: bool,
     *     postmortemDueDays: int|null,
     *     postmortemReviewRequired: bool
     * }|null
     */
    public function snapshot(IncidentPolicy $policy, IncidentSeverity $severity): ?array
    {
        $rule = $policy->ruleFor($severity);

        if ($rule === null) {
            return null;
        }

        return [
            'ackWithinMinutes' => $this->nullableInt($rule['ackWithinMinutes'] ?? null),
            'resolveWithinMinutes' => $this->nullableInt($rule['resolveWithinMinutes'] ?? null),
            'requireCommander' => (bool) ($rule['requireCommander'] ?? false),
            'stakeholderUpdateEveryMinutes' => $this->nullableInt($rule['communication']['stakeholderUpdateEveryMinutes'] ?? null),
            'statusPageUpdateRequired' => (bool) ($rule['communication']['statusPageUpdateRequired'] ?? false),
            'postmortemRequired' => (bool) ($rule['postmortem']['required'] ?? false),
            'postmortemDueDays' => $this->nullableInt($rule['postmortem']['dueDays'] ?? null),
            'postmortemReviewRequired' => (bool) ($rule['postmortem']['reviewRequired'] ?? false),
        ];
    }

    public function commanderUserId(IncidentPolicy $policy): ?string
    {
        foreach (AlertMatchContext::stringList($policy->teamIds ?? []) as $teamId) {
            $plan = OnCallPlan::query()->where('teamId', $teamId)->first();

            if ($plan === null) {
                continue;
            }

            $userId = $this->onCallResolver->at($plan)['layers'][0]['onCall']['userId'] ?? null;

            if (is_string($userId) && $userId !== '') {
                return $userId;
            }
        }

        return null;
    }

    public function schedule(Incident $incident): void
    {
        $sla = $incident->policySla ?? [];
        $ackWithin = $this->nullableInt($sla['ackWithinMinutes'] ?? null);
        $resolveWithin = $this->nullableInt($sla['resolveWithinMinutes'] ?? null);
        $stakeholderEvery = $this->nullableInt($sla['stakeholderUpdateEveryMinutes'] ?? null);
        $id = (string) $incident->id;

        if ($ackWithin !== null) {
            EnforceIncidentAckSlaJob::dispatch($id)->delay(now()->addMinutes($ackWithin));
        }

        if ($resolveWithin !== null) {
            EnforceIncidentResolveSlaJob::dispatch($id)->delay(now()->addMinutes($resolveWithin));
        }

        if ($stakeholderEvery !== null) {
            RemindIncidentStakeholderUpdateJob::dispatch($id)->delay(now()->addMinutes($stakeholderEvery));
        }
    }

    public function recordCommander(Incident $incident, string $commanderId, string $message): void
    {
        $user = User::query()->where('_id', $commanderId)->first();

        $this->timelineService->recordSystemEntry(
            $incident,
            IncidentTimelineEntryType::Updated,
            $message,
            null,
            [
                'commanderId' => $commanderId,
                'commanderName' => $user?->name,
            ],
        );
    }

    public function enforceAckSla(string $incidentId): void
    {
        $incident = $this->openIncident($incidentId);

        if ($incident === null) {
            return;
        }

        $minutes = $this->nullableInt(($incident->policySla ?? [])['ackWithinMinutes'] ?? null);

        if ($minutes === null || $incident->hasAllTeamsAcknowledged()) {
            return;
        }

        $this->timelineService->recordSystemEntry(
            $incident,
            IncidentTimelineEntryType::Escalation,
            'Ack SLA missed: not all teams acknowledged within '.$minutes.' minutes.',
            null,
            ['ackWithinMinutes' => $minutes],
        );

        $this->pager->nudge($incident);
    }

    public function enforceResolveSla(string $incidentId): void
    {
        $incident = $this->openIncident($incidentId);

        if ($incident === null) {
            return;
        }

        $minutes = $this->nullableInt(($incident->policySla ?? [])['resolveWithinMinutes'] ?? null);

        if ($minutes === null) {
            return;
        }

        $this->timelineService->recordSystemEntry(
            $incident,
            IncidentTimelineEntryType::Escalation,
            'Resolve SLA missed: incident still open after '.$minutes.' minutes.',
            null,
            ['resolveWithinMinutes' => $minutes],
        );

        $this->pager->nudge($incident);
    }

    public function remindStakeholders(string $incidentId): void
    {
        $incident = $this->openIncident($incidentId);

        if ($incident === null) {
            return;
        }

        $minutes = $this->nullableInt(($incident->policySla ?? [])['stakeholderUpdateEveryMinutes'] ?? null);

        if ($minutes === null) {
            return;
        }

        $recentUserUpdate = IncidentTimelineEntry::query()
            ->where('incidentId', (string) $incident->id)
            ->where('type', IncidentTimelineEntryType::Communication)
            ->where('source', IncidentTimelineEntrySource::User)
            ->where('occurredAt', '>=', now()->subMinutes($minutes))
            ->exists();

        if (! $recentUserUpdate) {
            $this->timelineService->recordSystemEntry(
                $incident,
                IncidentTimelineEntryType::Communication,
                'Stakeholder update due.',
                null,
                ['stakeholderUpdateEveryMinutes' => $minutes],
            );

            $this->pager->nudge($incident);
        }

        RemindIncidentStakeholderUpdateJob::dispatch((string) $incident->id)
            ->delay(now()->addMinutes($minutes));
    }

    public function assertCanResolve(Incident $incident): void
    {
        $sla = $incident->policySla ?? [];

        if (($sla['requireCommander'] ?? false) && empty($incident->commanderId)) {
            throw ValidationException::withMessages([
                'commanderId' => 'This policy requires a commander before the incident can be resolved.',
            ]);
        }

        if (($sla['statusPageUpdateRequired'] ?? false) && ! $this->hasPublicUpdate($incident)) {
            throw ValidationException::withMessages([
                'statusPage' => 'This policy requires a public status-page update before the incident can be resolved.',
            ]);
        }
    }

    public function onResolved(Incident $incident): void
    {
        $sla = $incident->policySla ?? [];

        if (! ($sla['postmortemRequired'] ?? false)) {
            return;
        }

        $dueDays = $this->nullableInt($sla['postmortemDueDays'] ?? null);
        $dueAt = $dueDays === null
            ? null
            : ($incident->resolvedAt?->copy() ?? now())->addDays($dueDays);

        $this->postMortemService->ensureRequiredDraft(
            $incident,
            $dueAt,
            $incident->resolvedBy,
        );
    }

    private function openIncident(string $incidentId): ?Incident
    {
        $incident = Incident::query()->where('_id', $incidentId)->first();

        if ($incident === null || ! in_array($incident->status, self::OPEN_STATUSES, true)) {
            return null;
        }

        return $incident;
    }

    private function hasPublicUpdate(Incident $incident): bool
    {
        return IncidentTimelineEntry::query()
            ->where('incidentId', (string) $incident->id)
            ->where('isPublic', true)
            ->exists();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
