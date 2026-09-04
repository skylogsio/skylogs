<?php

namespace App\Services;

use App\Enums\IncidentActionItemCategory;
use App\Enums\IncidentActionItemPriority;
use App\Enums\IncidentActionItemStatus;
use App\Models\Incident;
use App\Models\IncidentActionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Follow-up work produced by an incident.
 *
 * Per-incident access is inherited from the incident. The cross-incident listing is
 * scoped by ownership instead, because it answers "what is still on my plate" rather
 * than "what happened to this incident".
 */
class IncidentActionItemService
{
    public function __construct(private readonly TeamService $teamService) {}

    /**
     * @return Builder<IncidentActionItem>
     */
    public function query(?Incident $incident = null): Builder
    {
        $query = IncidentActionItem::query()->with(['ownerUser', 'incident']);

        if ($incident !== null) {
            $query->where('incidentId', (string) $incident->id);
        }

        return $query;
    }

    /**
     * Restricts the cross-incident listing to items the user owns, created, or that are
     * assigned to one of their teams.
     */
    public function applyVisibility(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $userTeamIds = array_map('strval', $this->teamService->userTeams($user)->pluck('_id')->all());

        $query->where(function (Builder $builder) use ($user, $userTeamIds) {
            $builder->where('ownerId', (string) $user->id)
                ->orWhere('createdBy', (string) $user->id);

            foreach ($userTeamIds as $teamId) {
                $builder->orWhere('teamId', $teamId);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(User $user, Incident $incident, array $validated): IncidentActionItem
    {
        $status = IncidentActionItemStatus::from($validated['status'] ?? IncidentActionItemStatus::Open->value);

        $actionItem = IncidentActionItem::create([
            ...$this->normalize($validated, $status),
            'incidentId' => (string) $incident->id,
            'postMortemId' => $validated['postMortemId'] ?? null,
            'completedAt' => $status === IncidentActionItemStatus::Done ? now() : null,
            'createdBy' => $user->id,
        ]);

        $actionItem->load('ownerUser');

        return $actionItem;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(IncidentActionItem $actionItem, array $validated): IncidentActionItem
    {
        $status = IncidentActionItemStatus::from($validated['status'] ?? $actionItem->status->value);

        $actionItem->update([
            ...$this->normalize($validated, $status),
            'postMortemId' => $validated['postMortemId'] ?? null,
            'completedAt' => $this->completedAt($actionItem, $status),
        ]);

        $actionItem->load('ownerUser');

        return $actionItem;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalize(array $validated, IncidentActionItemStatus $status): array
    {
        return [
            'title' => $validated['title'],
            'description' => (string) ($validated['description'] ?? ''),
            'ownerId' => $validated['ownerId'] ?? null,
            'teamId' => $validated['teamId'] ?? null,
            'priority' => IncidentActionItemPriority::from(
                $validated['priority'] ?? IncidentActionItemPriority::Medium->value,
            ),
            'category' => IncidentActionItemCategory::from(
                $validated['category'] ?? IncidentActionItemCategory::Other->value,
            ),
            'status' => $status,
            'dueAt' => $this->parseDate($validated['dueAt'] ?? null),
        ];
    }

    /**
     * Stamped when the item first reaches `done`, cleared when it is reopened.
     */
    private function completedAt(IncidentActionItem $actionItem, IncidentActionItemStatus $status): ?Carbon
    {
        if ($status !== IncidentActionItemStatus::Done) {
            return null;
        }

        return $actionItem->completedAt ?? now();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }
}
