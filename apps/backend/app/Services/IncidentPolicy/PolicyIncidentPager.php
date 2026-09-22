<?php

namespace App\Services\IncidentPolicy;

use App\Enums\IncidentStatus;
use App\Enums\IncidentTimelineEntryType;
use App\Jobs\PageIncidentLayerJob;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\Incident;
use App\Models\IncidentPolicy;
use App\Models\Notify;
use App\Models\OnCallPlan;
use App\Models\Team;
use App\Services\IncidentTimelineService;
use App\Services\NotifyMessageComposer;
use App\Services\OnCallResolver;
use App\Support\NotifyMessagePayload;

class PolicyIncidentPager
{
    /**
     * @var list<IncidentStatus>
     */
    private const PAGEABLE_STATUSES = [
        IncidentStatus::Open,
        IncidentStatus::Investigating,
    ];

    public function __construct(
        private readonly OnCallResolver $onCallResolver,
        private readonly IncidentTimelineService $timelineService,
    ) {}

    /**
     * Page the SEV rule's notify endpoints and the current on-call of each team.
     * When useLayers is true, later layers are queued after each layer's delay.
     */
    public function page(Incident $incident, IncidentPolicy $policy, AlertMatchContext $context): void
    {
        $rule = $policy->ruleFor($incident->severity);

        if ($rule === null) {
            return;
        }

        $useLayers = (bool) ($rule['escalation']['useLayers'] ?? true);
        $notifyEndpointIds = $this->stringList($rule['notifyEndpointIds'] ?? []);
        $onCallEndpointIds = [];

        foreach ($this->stringList($policy->teamIds ?? []) as $teamId) {
            $resolved = $this->resolvePlan($teamId);

            if ($resolved === null) {
                continue;
            }

            $layers = $resolved['layers'];
            $endpointId = $layers[0]['onCall']['endpoint']['id'] ?? null;

            if (is_string($endpointId) && $endpointId !== '') {
                $onCallEndpointIds[] = $endpointId;
            }

            if ($useLayers) {
                $this->scheduleLaterLayers($incident, $policy, $teamId, $layers);
            }
        }

        $endpointIds = $this->stringList([...$notifyEndpointIds, ...$onCallEndpointIds]);

        if ($endpointIds === []) {
            return;
        }

        $this->dispatchPage(
            $incident,
            $policy,
            $context->alertRuleId,
            $endpointIds,
            'Paged from policy '.$policy->name.' as '.$incident->severity->value.'.',
            [
                'endpointIds' => $endpointIds,
                'layer' => 1,
            ],
        );
    }

    /**
     * Re-page notify endpoints and the current on-call without walking later layers.
     */
    public function nudge(Incident $incident): void
    {
        if (! in_array($incident->status, self::PAGEABLE_STATUSES, true) || empty($incident->policyId)) {
            return;
        }

        $policy = IncidentPolicy::query()->where('_id', $incident->policyId)->first();
        $rule = $policy?->ruleFor($incident->severity);

        if ($policy === null || $rule === null) {
            return;
        }

        $onCallEndpointIds = [];

        foreach ($this->stringList($policy->teamIds ?? []) as $teamId) {
            if ($incident->hasTeamAcknowledged($teamId)) {
                continue;
            }

            $resolved = $this->resolvePlan($teamId);
            $endpointId = $resolved['layers'][0]['onCall']['endpoint']['id'] ?? null;

            if (is_string($endpointId) && $endpointId !== '') {
                $onCallEndpointIds[] = $endpointId;
            }
        }

        $endpointIds = $this->stringList([
            ...($rule['notifyEndpointIds'] ?? []),
            ...$onCallEndpointIds,
        ]);

        if ($endpointIds === []) {
            return;
        }

        $this->dispatchPage(
            $incident,
            $policy,
            $this->stringList($incident->alertRuleIds ?? [])[0] ?? null,
            $endpointIds,
            null,
            [
                'endpointIds' => $endpointIds,
                'nudge' => true,
            ],
        );
    }

    public function pageLayer(string $incidentId, string $policyId, string $teamId, int $layerLevel): void
    {
        $incident = Incident::query()->where('_id', $incidentId)->first();
        $policy = IncidentPolicy::query()->where('_id', $policyId)->first();

        if ($incident === null || $policy === null) {
            return;
        }

        if (! in_array($incident->status, self::PAGEABLE_STATUSES, true)) {
            return;
        }

        if ($incident->hasTeamAcknowledged($teamId)) {
            $this->recordSkippedLayer($incident, $policy, $teamId, $layerLevel);

            return;
        }

        $rule = $policy->ruleFor($incident->severity);

        if ($rule === null || ! ($rule['escalation']['useLayers'] ?? true)) {
            return;
        }

        $teamIds = $this->stringList([
            ...($policy->teamIds ?? []),
            ...($incident->teamIds ?? []),
        ]);

        if (! in_array($teamId, $teamIds, true)) {
            return;
        }

        $resolved = $this->resolvePlan($teamId);

        if ($resolved === null) {
            return;
        }

        $layer = collect($resolved['layers'])->first(
            fn (array $candidate): bool => (int) $candidate['level'] === $layerLevel,
        );

        $endpointId = $layer['onCall']['endpoint']['id'] ?? null;

        if (! is_string($endpointId) || $endpointId === '') {
            return;
        }

        $alertRuleId = $this->stringList($incident->alertRuleIds ?? [])[0] ?? null;

        $this->dispatchPage(
            $incident,
            $policy,
            $alertRuleId,
            [$endpointId],
            'On-call layer '.$layerLevel.' paged.',
            [
                'endpointIds' => [$endpointId],
                'layer' => $layerLevel,
                'teamId' => $teamId,
            ],
        );
    }

    /**
     * @param  list<array{level: int, escalateAfterMinutes: int, onCall: mixed}>  $layers
     */
    private function scheduleLaterLayers(Incident $incident, IncidentPolicy $policy, string $teamId, array $layers): void
    {
        $elapsedMinutes = 0;

        foreach ($layers as $index => $layer) {
            $wait = max(0, (int) ($layer['escalateAfterMinutes'] ?? 0));

            if ($index === 0) {
                $elapsedMinutes += $wait;

                continue;
            }

            $pending = PageIncidentLayerJob::dispatch(
                (string) $incident->id,
                (string) $policy->id,
                $teamId,
                (int) $layer['level'],
            );

            if ($elapsedMinutes > 0) {
                $pending->delay(now()->addMinutes($elapsedMinutes));
            }

            $elapsedMinutes += $wait;
        }
    }

    private function recordSkippedLayer(Incident $incident, IncidentPolicy $policy, string $teamId, int $layerLevel): void
    {
        $teamName = Team::query()->where('_id', $teamId)->value('name') ?: $teamId;
        $unackedNames = $this->teamNames($incident->unacknowledgedTeamIds());
        $message = 'On-call layer '.$layerLevel.' skipped for '.$teamName.'; team already acknowledged.';

        if ($unackedNames !== []) {
            $message .= ' Remaining: '.$this->joinNames($unackedNames).' '.($this->have($unackedNames)).' not acknowledged.';
        }

        $this->timelineService->recordSystemEntry(
            $incident,
            IncidentTimelineEntryType::Escalation,
            $message,
            null,
            [
                'layer' => $layerLevel,
                'teamId' => $teamId,
                'skipped' => true,
                'unacknowledgedTeamIds' => $incident->unacknowledgedTeamIds(),
                'policyId' => (string) $policy->id,
            ],
        );
    }

    /**
     * @param  list<string>  $teamIds
     * @return list<string>
     */
    private function teamNames(array $teamIds): array
    {
        if ($teamIds === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            'strval',
            Team::query()->whereIn('_id', $teamIds)->pluck('name')->all(),
        )));
    }

    /**
     * @param  list<string>  $names
     */
    private function joinNames(array $names): string
    {
        if (count($names) <= 1) {
            return $names[0] ?? '';
        }

        $last = array_pop($names);

        return implode(', ', $names).' and '.$last;
    }

    /**
     * @param  list<string>  $names
     */
    private function have(array $names): string
    {
        return count($names) === 1 ? 'has' : 'have';
    }

    /**
     * @param  list<string>  $endpointIds
     * @param  array<string, mixed>  $meta
     */
    private function dispatchPage(
        Incident $incident,
        IncidentPolicy $policy,
        ?string $alertRuleId,
        array $endpointIds,
        ?string $timelineMessage,
        array $meta,
    ): void {
        $alertRule = $alertRuleId
            ? AlertRule::query()->where('_id', $alertRuleId)->first()
            : null;

        $notify = new Notify;
        $notify->type = SendNotifyJob::INCIDENT_POLICY_PAGE;
        $notify->alertRuleId = $alertRule?->id;
        $notify->incidentId = (string) $incident->id;
        $notify->endpointIds = $endpointIds;
        $notify->status = Notify::STATUS_CREATED;

        if ($alertRule !== null) {
            $notify->alert = $alertRule->toArray();
            $notify->messages = NotifyMessageComposer::buildMessages($alertRule, $alertRule);
        } else {
            $notify->alert = ['title' => $incident->title];
            $notify->messages = NotifyMessagePayload::fromBody((string) $incident->title)->toArray();
        }

        $notify->save();
        SendNotifyJob::dispatch($notify);

        if ($timelineMessage === null) {
            return;
        }

        $this->timelineService->recordSystemEntry(
            $incident,
            IncidentTimelineEntryType::Escalation,
            $timelineMessage,
            null,
            [
                ...$meta,
                'policyId' => (string) $policy->id,
            ],
        );
    }

    /**
     * @return array{layers: list<array<string, mixed>>}|null
     */
    private function resolvePlan(string $teamId): ?array
    {
        $plan = OnCallPlan::query()->where('teamId', $teamId)->first();

        if ($plan === null) {
            return null;
        }

        return $this->onCallResolver->at($plan);
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        return AlertMatchContext::stringList($values);
    }
}
