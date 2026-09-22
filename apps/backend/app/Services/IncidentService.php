<?php

namespace App\Services;

use App\Enums\IncidentActionItemStatus;
use App\Enums\IncidentDocumentAttachableType;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentTimelineEntryType;
use App\Models\Incident;
use App\Models\IncidentActionItem;
use App\Models\IncidentDocument;
use App\Models\IncidentTimelineEntry;
use App\Models\PostMortem;
use App\Models\Team;
use App\Models\User;
use App\Services\IncidentPolicy\PolicyIncidentFollowThrough;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class IncidentService
{
    public function __construct(
        private readonly TeamService $teamService,
        private readonly IncidentTimelineService $timelineService,
        private readonly IncidentDocumentService $documentService,
        private readonly PostMortemService $postMortemService,
        private readonly PolicyIncidentFollowThrough $followThrough,
    ) {}

    /**
     * Whether the user is a member or owner of the given team.
     */
    public function isTeamMember(User $user, Team $team): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->id === $team->ownerId
            || $user->_id === $team->ownerId
            || in_array($user->id, $team->userIds ?? []);
    }

    /**
     * Whether the user is an owner of the given team.
     */
    public function isTeamOwner(User $user, Team $team): bool
    {
        return $user->id === $team->ownerId || $user->_id === $team->ownerId;
    }

    /**
     * Create: admin, or member/owner of every team in teamIds.
     *
     * @param  list<string>  $teamIds
     */
    public function canCreate(User $user, array $teamIds): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $teams = Team::query()->whereIn('_id', $teamIds)->get();

        if ($teams->count() !== count($teamIds)) {
            return false;
        }

        return $teams->every(fn (Team $team) => $this->isTeamMember($user, $team));
    }

    /**
     * View/ack/resolve: admin, creator, or member of any assigned team.
     */
    public function canView(User $user, Incident $incident): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->id === $incident->createdBy || $user->_id === $incident->createdBy) {
            return true;
        }

        $userTeamIds = array_map(
            'strval',
            $this->teamService->userTeams($user)->pluck('_id')->all(),
        );
        $incidentTeamIds = array_map('strval', $incident->teamIds ?? []);

        return count(array_intersect($incidentTeamIds, $userTeamIds)) > 0;
    }

    /**
     * Update/delete: admin, creator, or owner of an assigned team.
     */
    public function canEdit(User $user, Incident $incident): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->id === $incident->createdBy || $user->_id === $incident->createdBy) {
            return true;
        }

        $teams = Team::query()->whereIn('_id', $incident->teamIds ?? [])->get();

        return $teams->contains(fn (Team $team) => $this->isTeamOwner($user, $team));
    }

    public function canAcknowledge(User $user, Incident $incident): bool
    {
        if ($incident->status === IncidentStatus::Resolved || ! $this->canView($user, $incident)) {
            return false;
        }

        return $this->acknowledgeableTeamIds($user, $incident) !== [];
    }

    public function canResolve(User $user, Incident $incident): bool
    {
        return $incident->status !== IncidentStatus::Resolved
            && $this->canView($user, $incident);
    }

    public function applyAccessFlags(User $user, Incident $incident): Incident
    {
        $incident->setAttribute('canEdit', $this->canEdit($user, $incident));
        $incident->setAttribute('canDelete', $this->canEdit($user, $incident));
        $incident->setAttribute('canAcknowledge', $this->canAcknowledge($user, $incident));
        $incident->setAttribute('canResolve', $this->canResolve($user, $incident));

        return $incident;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, UploadedFile|null>  $documentFiles
     */
    public function create(User $user, array $validated, array $documentFiles = []): Incident
    {
        $now = now();
        $startedAt = $this->parseDate($validated['startedAt'] ?? null) ?? $now;
        $detectedAt = $this->parseDate($validated['detectedAt'] ?? null) ?? $now;
        $resolvedAt = $this->parseDate($validated['resolvedAt'] ?? null);

        $incident = Incident::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'teamIds' => $validated['teamIds'],
            'tags' => $this->normalizeTags($validated['tags'] ?? []),
            'startedAt' => $startedAt,
            'detectedAt' => $detectedAt,
            'resolvedAt' => $resolvedAt,
            'resolvedBy' => $resolvedAt ? $user->id : null,
            'alertRuleIds' => $validated['alertRuleIds'] ?? [],
            'severity' => $validated['severity'],
            'status' => $resolvedAt ? IncidentStatus::Resolved : IncidentStatus::Open,
            'source' => IncidentSource::Manual,
            'createdBy' => $user->id,
            'acknowledgements' => [],
        ]);

        $this->timelineService->recordSystemEntry(
            $incident,
            IncidentTimelineEntryType::Created,
            'Incident reported as '.$incident->severity->value.'.',
            $user,
            ['severity' => $incident->severity->value, 'status' => $incident->status->value],
        );

        if ($resolvedAt !== null) {
            $this->recordResolution($incident, $user);
        }

        return $this->attachNestedDocumentation($user, $incident, $validated, $documentFiles);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, UploadedFile|null>  $documentFiles
     */
    public function update(User $user, Incident $incident, array $validated, array $documentFiles = []): Incident
    {
        $resolvedAt = array_key_exists('resolvedAt', $validated)
            ? $this->parseDate($validated['resolvedAt'])
            : $incident->resolvedAt;

        $becameResolved = $resolvedAt !== null && $incident->status !== IncidentStatus::Resolved;

        if (array_key_exists('commanderId', $validated)) {
            $incident->commanderId = $validated['commanderId'];
        }

        if ($becameResolved) {
            $this->followThrough->assertCanResolve($incident);
        }

        $updates = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'teamIds' => $validated['teamIds'],
            'tags' => $this->normalizeTags($validated['tags'] ?? []),
            'startedAt' => $this->parseDate($validated['startedAt'] ?? null) ?? $incident->startedAt,
            'detectedAt' => $this->parseDate($validated['detectedAt'] ?? null) ?? $incident->detectedAt,
            'resolvedAt' => $resolvedAt,
            'resolvedBy' => $becameResolved ? $user->id : $incident->resolvedBy,
            'alertRuleIds' => $validated['alertRuleIds'] ?? [],
            'severity' => $validated['severity'],
            'status' => $becameResolved ? IncidentStatus::Resolved : $incident->status,
        ];

        if (array_key_exists('commanderId', $validated)) {
            $updates['commanderId'] = $validated['commanderId'];
        }

        $previousCommanderId = $incident->commanderId;
        $incident->update($updates);

        if (array_key_exists('commanderId', $validated) && $validated['commanderId'] !== $previousCommanderId && $validated['commanderId'] !== null) {
            $this->followThrough->recordCommander(
                $incident,
                (string) $validated['commanderId'],
                'Commander assigned.',
            );
        }

        if ($becameResolved) {
            $this->recordResolution($incident, $user);
            $this->followThrough->onResolved($incident->fresh() ?? $incident);
        }

        return $this->attachNestedDocumentation($user, $incident, $validated, $documentFiles);
    }

    /**
     * Optional postmortem (upsert) then additive documents, so a create/update form can
     * attach both in one request. Nested endpoints remain the dedicated write path.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<int, UploadedFile|null>  $documentFiles
     */
    private function attachNestedDocumentation(
        User $user,
        Incident $incident,
        array $validated,
        array $documentFiles,
    ): Incident {
        if (isset($validated['postMortem']) && is_array($validated['postMortem'])) {
            $this->postMortemService->upsert($user, $incident, $validated['postMortem']);
        }

        foreach ($validated['documents'] ?? [] as $index => $document) {
            if (! is_array($document)) {
                continue;
            }

            $file = $documentFiles[$index] ?? null;
            $attachableType = $document['attachableType'] ?? IncidentDocumentAttachableType::Incident->value;
            $postMortemId = null;

            if ($attachableType === IncidentDocumentAttachableType::PostMortem->value) {
                $postMortem = $this->postMortemService->forIncident($incident);

                if ($postMortem === null) {
                    throw ValidationException::withMessages([
                        'documents.'.$index.'.attachableType' => 'This incident has no postmortem yet, so a document cannot be attached to one.',
                    ]);
                }

                $postMortemId = (string) $postMortem->id;
            }

            $this->documentService->create(
                $user,
                $incident,
                $document,
                $file instanceof UploadedFile ? $file : null,
                $postMortemId,
            );
        }

        $incident->load(['createdByUser', 'postMortem']);
        $incident->setAttribute('counts', $this->documentationCounts($incident));

        return $this->applyAccessFlags($user, $incident);
    }

    /**
     * How much documentation an incident already carries, so the detail page can badge its
     * tabs without fetching each sub-resource.
     *
     * @return array{timelineEntries: int, documents: int, actionItems: int, openActionItems: int}
     */
    public function documentationCounts(Incident $incident): array
    {
        $incidentId = (string) $incident->id;

        return [
            'timelineEntries' => IncidentTimelineEntry::query()->where('incidentId', $incidentId)->count(),
            'documents' => IncidentDocument::query()->where('incidentId', $incidentId)->count(),
            'actionItems' => IncidentActionItem::query()->where('incidentId', $incidentId)->count(),
            'openActionItems' => IncidentActionItem::query()
                ->where('incidentId', $incidentId)
                ->whereNotIn('status', [
                    IncidentActionItemStatus::Done->value,
                    IncidentActionItemStatus::Cancelled->value,
                ])
                ->count(),
        ];
    }

    /**
     * Removes the incident together with everything documented against it, including the
     * stored files, so deleting an incident does not leave orphans behind.
     */
    public function delete(Incident $incident): void
    {
        IncidentActionItem::query()->where('incidentId', (string) $incident->id)->delete();
        PostMortem::query()->where('incidentId', (string) $incident->id)->delete();
        $this->documentService->deleteForIncident($incident);
        $this->timelineService->deleteForIncident($incident);

        $incident->delete();
    }

    public function acknowledge(User $user, Incident $incident, ?string $teamId = null): Incident
    {
        $teamIds = $this->acknowledgeableTeamIds($user, $incident, $teamId);

        if ($teamIds === []) {
            abort(403);
        }

        $acknowledgements = $incident->acknowledgements ?? [];
        $now = now();

        foreach ($teamIds as $id) {
            $acknowledgements[] = [
                'teamId' => $id,
                'acknowledgedBy' => $user->id,
                'acknowledgedAt' => $now,
            ];
        }

        $updates = ['acknowledgements' => array_values($acknowledgements)];
        $previousStatus = $incident->status;

        if ($previousStatus === IncidentStatus::Open) {
            $updates['status'] = IncidentStatus::Investigating;
        }

        $incident->update($updates);

        if (empty($incident->commanderId) && (($incident->policySla ?? [])['requireCommander'] ?? false)) {
            $incident->update(['commanderId' => $user->id]);
            $this->followThrough->recordCommander(
                $incident,
                (string) $user->id,
                'Commander set from acknowledgement.',
            );
        }

        $teamNames = Team::query()->whereIn('_id', $teamIds)->pluck('name')->all();
        $ackFor = 'Acknowledged for '.(implode(', ', $teamNames) ?: implode(', ', $teamIds)).'.';
        $remainder = $this->followThrough->remainderMessage($incident);
        $message = $remainder === null ? $ackFor : $ackFor.' '.$remainder;

        $this->timelineService->recordSystemEntry(
            $incident,
            IncidentTimelineEntryType::Acknowledged,
            $message,
            $user,
            [
                'teamIds' => $teamIds,
                'unacknowledgedTeamIds' => $incident->unacknowledgedTeamIds(),
            ],
        );

        $this->followThrough->notifyRemainingAfterAck($incident);

        $this->recordStatusChange($incident, $previousStatus, $user);

        $incident->load('createdByUser');

        return $this->applyAccessFlags($user, $incident);
    }

    public function resolve(User $user, Incident $incident, ?string $resolvedAt = null): Incident
    {
        $this->followThrough->assertCanResolve($incident);

        $incident->update([
            'status' => IncidentStatus::Resolved,
            'resolvedBy' => $user->id,
            'resolvedAt' => $this->parseDate($resolvedAt) ?? now(),
        ]);

        $this->recordResolution($incident, $user);
        $this->followThrough->onResolved($incident->fresh() ?? $incident);

        $incident->load('createdByUser');

        return $this->applyAccessFlags($user, $incident);
    }

    /**
     * Teams the user may still acknowledge on this incident.
     *
     * @return list<string>
     */
    public function acknowledgeableTeamIds(User $user, Incident $incident, ?string $teamId = null): array
    {
        $assignedTeamIds = array_map('strval', $incident->teamIds ?? []);

        if ($teamId !== null) {
            if (! in_array($teamId, $assignedTeamIds, true) || $incident->hasTeamAcknowledged($teamId)) {
                return [];
            }

            if ($user->isAdmin()) {
                return [$teamId];
            }

            $team = Team::query()->where('_id', $teamId)->first();

            return $team && $this->isTeamMember($user, $team) ? [$teamId] : [];
        }

        $candidateIds = $user->isAdmin()
            ? $assignedTeamIds
            : array_values(array_intersect(
                $assignedTeamIds,
                array_map('strval', $this->teamService->userTeams($user)->pluck('_id')->all()),
            ));

        return array_values(array_filter(
            $candidateIds,
            fn (string $id) => ! $incident->hasTeamAcknowledged($id),
        ));
    }

    private function recordResolution(Incident $incident, User $user): void
    {
        $this->timelineService->recordSystemEntry(
            $incident,
            IncidentTimelineEntryType::Resolved,
            'Incident resolved.',
            $user,
            ['resolvedAt' => $incident->resolvedAt?->toISOString()],
        );
    }

    private function recordStatusChange(Incident $incident, IncidentStatus $previousStatus, User $user): void
    {
        if ($previousStatus === $incident->status) {
            return;
        }

        $this->timelineService->recordSystemEntry(
            $incident,
            IncidentTimelineEntryType::StatusChanged,
            'Status changed from '.$previousStatus->value.' to '.$incident->status->value.'.',
            $user,
            ['from' => $previousStatus->value, 'to' => $incident->status->value],
        );
    }

    /**
     * @param  list<string>|null  $tags
     * @return list<string>
     */
    private function normalizeTags(?array $tags): array
    {
        return collect($tags ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        return Carbon::parse($value);
    }
}
