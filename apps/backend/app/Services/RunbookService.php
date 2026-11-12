<?php

namespace App\Services;

use App\Enums\RunbookSourceType;
use App\Enums\RunbookStatus;
use App\Models\Runbook;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class RunbookService
{
    public function __construct(private readonly TeamService $teamService) {}

    /**
     * View: admin, the author, or a member of an owning team.
     */
    public function canView(User $user, Runbook $runbook): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->id === $runbook->createdBy || $user->_id === $runbook->createdBy) {
            return true;
        }

        $userTeamIds = array_map('strval', $this->teamService->userTeams($user)->pluck('_id')->all());
        $runbookTeamIds = array_map('strval', $runbook->teamIds ?? []);

        return count(array_intersect($runbookTeamIds, $userTeamIds)) > 0;
    }

    /**
     * Update/delete: admin, the author, or an owner of an owning team.
     */
    public function canEdit(User $user, Runbook $runbook): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->id === $runbook->createdBy || $user->_id === $runbook->createdBy) {
            return true;
        }

        return Team::query()
            ->whereIn('_id', $runbook->teamIds ?? [])
            ->get()
            ->contains(fn (Team $team) => $user->id === $team->ownerId || $user->_id === $team->ownerId);
    }

    public function applyAccessFlags(User $user, Runbook $runbook): Runbook
    {
        $canEdit = $this->canEdit($user, $runbook);

        $runbook->setAttribute('canEdit', $canEdit);
        $runbook->setAttribute('canDelete', $canEdit);

        return $runbook;
    }

    /**
     * Restricts a listing to the runbooks the user is allowed to see.
     */
    public function applyVisibility(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $userTeamIds = array_map('strval', $this->teamService->userTeams($user)->pluck('_id')->all());

        $query->where(function (Builder $builder) use ($user, $userTeamIds) {
            $builder->where('createdBy', (string) $user->id);

            foreach ($userTeamIds as $teamId) {
                $builder->orWhere('teamIds', $teamId);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(User $user, array $validated): Runbook
    {
        $runbook = Runbook::create([
            ...$this->normalize($validated),
            'slug' => $this->uniqueSlug(($validated['slug'] ?? null) ?: $validated['name']),
            'version' => 1,
            'createdBy' => $user->id,
            'updatedBy' => $user->id,
        ]);

        $runbook->load('createdByUser');

        return $this->applyAccessFlags($user, $runbook);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(User $user, Runbook $runbook, array $validated): Runbook
    {
        $runbook->update([
            ...$this->normalize($validated),
            'slug' => $this->uniqueSlug(($validated['slug'] ?? null) ?: $validated['name'], $runbook->id),
            'version' => (int) $runbook->version + 1,
            'updatedBy' => $user->id,
        ]);

        $runbook->load('createdByUser');

        return $this->applyAccessFlags($user, $runbook);
    }

    /**
     * Only the body matching `sourceType` is stored, so switching a runbook from steps to
     * a wiki link does not leave a stale copy of the old body behind.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalize(array $validated): array
    {
        $sourceType = RunbookSourceType::from($validated['sourceType']);
        $appliesTo = $validated['appliesTo'] ?? [];

        return [
            'name' => $validated['name'],
            'description' => (string) ($validated['description'] ?? ''),
            'teamIds' => $this->stringList($validated['teamIds']),
            'tags' => $this->stringList($validated['tags'] ?? []),
            'status' => RunbookStatus::from($validated['status'] ?? RunbookStatus::Draft->value),
            'sourceType' => $sourceType,
            'content' => $sourceType === RunbookSourceType::Markdown ? $validated['content'] : null,
            'externalUrl' => $sourceType === RunbookSourceType::ExternalUrl ? $validated['externalUrl'] : null,
            'steps' => $sourceType === RunbookSourceType::Steps ? $this->normalizeSteps($validated['steps']) : [],
            'appliesTo' => [
                'serviceIds' => $this->stringList($appliesTo['serviceIds'] ?? []),
                'alertRuleIds' => $this->stringList($appliesTo['alertRuleIds'] ?? []),
                'tags' => $this->stringList($appliesTo['tags'] ?? []),
                'severities' => $this->stringList($appliesTo['severities'] ?? []),
            ],
            'reviewIntervalDays' => isset($validated['reviewIntervalDays'])
                ? (int) $validated['reviewIntervalDays']
                : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return list<array{title: string, description: string|null, command: string|null, expectedResult: string|null}>
     */
    private function normalizeSteps(array $steps): array
    {
        return array_values(array_map(fn (array $step) => [
            'title' => (string) $step['title'],
            'description' => $step['description'] ?? null,
            'command' => $step['command'] ?? null,
            'expectedResult' => $step['expectedResult'] ?? null,
        ], $steps));
    }

    private function uniqueSlug(string $source, ?string $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'runbook';
        $slug = $base;
        $suffix = 2;

        while ($this->slugTaken($slug, $ignoreId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugTaken(string $slug, ?string $ignoreId): bool
    {
        $query = Runbook::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('_id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        return array_values(array_unique(array_map('strval', $values)));
    }
}
