<?php

namespace App\Services;

use App\Models\Endpoint;
use App\Models\OnCallPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OnCallPlanService
{
    public const DEFAULT_ESCALATE_AFTER_MINUTES = 15;

    public function __construct(
        private readonly TeamService $teamService,
        private readonly OnCallResolver $resolver,
    ) {}

    public function canView(User $user, Team $team): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->isTeamMember($user, $team);
    }

    public function canEdit(User $user, Team $team): bool
    {
        return $this->teamService->canUpdateTeam($user, $team);
    }

    public function isTeamMember(User $user, Team $team): bool
    {
        if ($user->id === $team->ownerId || $user->_id === $team->ownerId) {
            return true;
        }

        return in_array((string) $user->id, $this->teamMemberIds($team), true);
    }

    /**
     * @return list<string>
     */
    public function teamMemberIds(Team $team): array
    {
        return array_values(array_unique(array_filter(array_map(
            'strval',
            [(string) $team->ownerId, ...($team->userIds ?? [])],
        ))));
    }

    public function findForTeam(Team $team): ?OnCallPlan
    {
        return OnCallPlan::query()->where('teamId', (string) $team->id)->first();
    }

    public function applyAccessFlags(User $user, OnCallPlan $plan, Team $team): OnCallPlan
    {
        $canEdit = $this->canEdit($user, $team);

        $plan->setAttribute('canEdit', $canEdit);
        $plan->setAttribute('canDelete', $canEdit);
        $plan->setAttribute('isComplete', $this->isComplete($plan));
        $plan->setRelation('team', $team);

        return $plan;
    }

    public function isComplete(OnCallPlan $plan): bool
    {
        $layers = $plan->layers ?? [];

        if ($layers === []) {
            return false;
        }

        foreach ($layers as $layer) {
            if (empty($layer['entries'])) {
                return false;
            }
        }

        $rosterUserIds = $this->rosterUserIds($layers);

        if ($rosterUserIds === []) {
            return false;
        }

        $usersWithOnCall = Endpoint::query()
            ->whereIn('userId', $rosterUserIds)
            ->where('onCall', true)
            ->pluck('userId')
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->all();

        return count(array_diff($rosterUserIds, $usersWithOnCall)) === 0;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<array<string, mixed>>  $layers
     */
    public function create(User $user, Team $team, array $validated, array $layers): OnCallPlan
    {
        if ($this->findForTeam($team) !== null) {
            throw ValidationException::withMessages([
                'teamId' => ['This team already has an on-call plan.'],
            ]);
        }

        $plan = OnCallPlan::create([
            'teamId' => (string) $team->id,
            ...$this->normalize($team, $validated, $layers),
        ]);

        return $this->applyAccessFlags($user, $plan, $team);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<array<string, mixed>>  $layers
     */
    public function update(User $user, Team $team, OnCallPlan $plan, array $validated, array $layers): OnCallPlan
    {
        $plan->update($this->normalize($team, $validated, $layers));

        return $this->applyAccessFlags($user, $plan->fresh(), $team);
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return list<array<string, mixed>>
     */
    public function currentForTeams(Collection $teams, ?Carbon $at = null): array
    {
        $teamIds = $teams->map(fn (Team $team) => (string) $team->id)->all();
        $plans = OnCallPlan::query()
            ->whereIn('teamId', $teamIds)
            ->get()
            ->keyBy(fn (OnCallPlan $plan) => (string) $plan->teamId);

        $blocks = [];

        foreach ($teams as $team) {
            $plan = $plans->get((string) $team->id);

            if ($plan === null) {
                $blocks[] = [
                    'teamId' => (string) $team->id,
                    'teamName' => $team->name,
                    'plan' => null,
                    'at' => ($at ?? Carbon::now())->toIso8601String(),
                    'timezone' => null,
                    'layers' => [],
                ];

                continue;
            }

            $blocks[] = [
                ...$this->resolver->at($plan, $at),
                'teamName' => $team->name,
            ];
        }

        return $blocks;
    }

    /**
     * @param  Collection<int, Team>  $visibleTeams
     * @param  list<string>|null  $requestedTeamIds
     * @return Collection<int, Team>
     */
    public function teamsForCurrentLookup(User $user, Collection $visibleTeams, ?array $requestedTeamIds): Collection
    {
        if ($requestedTeamIds === null || $requestedTeamIds === []) {
            return $visibleTeams->values();
        }

        $requested = array_map('strval', $requestedTeamIds);
        $visibleIds = $visibleTeams->map(fn (Team $team) => (string) $team->id)->all();

        $unknown = array_values(array_diff($requested, $visibleIds));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'teamIds' => ['One or more teams were not found or are not visible.'],
            ]);
        }

        return $visibleTeams
            ->filter(fn (Team $team) => in_array((string) $team->id, $requested, true))
            ->values();
    }

    public function visibleTeams(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Team::query()->get();
        }

        return $this->teamService->userTeams($user);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<array<string, mixed>>  $layers
     * @return array{name: string, timezone: string, layers: list<array<string, mixed>>}
     */
    public function normalize(Team $team, array $validated, array $layers): array
    {
        $layers = $this->applyLayerDelays($layers, $validated['layerDelays'] ?? null);
        $layers = $this->normalizeLayers($layers);
        $this->assertNoOverlaps($layers);
        $this->assertUsersAreMembers($team, $this->rosterUserIds($layers));

        return [
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'layers' => $layers,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $layers
     * @return list<array<string, mixed>>
     */
    private function normalizeLayers(array $layers): array
    {
        $normalized = [];

        foreach ($layers as $layer) {
            $entriesByUser = [];

            foreach ($layer['entries'] ?? [] as $entry) {
                $userId = (string) $entry['userId'];
                $windows = array_map(fn (array $window): array => [
                    'daysOfWeek' => array_values(array_unique(array_map('intval', $window['daysOfWeek']))),
                    'startTime' => $window['startTime'],
                    'endTime' => $window['endTime'],
                ], $entry['windows'] ?? []);

                if (! isset($entriesByUser[$userId])) {
                    $entriesByUser[$userId] = [];
                }

                $entriesByUser[$userId] = [...$entriesByUser[$userId], ...$windows];
            }

            $entries = [];

            foreach ($entriesByUser as $userId => $windows) {
                $entries[] = [
                    'userId' => $userId,
                    'windows' => $windows,
                ];
            }

            $normalized[] = [
                'level' => (int) $layer['level'],
                'escalateAfterMinutes' => (int) ($layer['escalateAfterMinutes'] ?? self::DEFAULT_ESCALATE_AFTER_MINUTES),
                'entries' => $entries,
            ];
        }

        usort($normalized, fn (array $left, array $right): int => $left['level'] <=> $right['level']);

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $layers
     */
    private function assertNoOverlaps(array $layers): void
    {
        foreach ($layers as $index => $layer) {
            $intervalsByDay = [];

            foreach ($layer['entries'] as $entry) {
                foreach ($entry['windows'] as $window) {
                    $start = $this->resolver->timeToMinutes($window['startTime']);
                    $end = $this->resolver->timeToMinutes($window['endTime']);

                    if ($start >= $end) {
                        throw ValidationException::withMessages([
                            "layers.{$index}.entries" => ['Each window must start before it ends. Overnight wrap is not supported.'],
                        ]);
                    }

                    foreach ($window['daysOfWeek'] as $day) {
                        $intervalsByDay[$day][] = [$start, $end];
                    }
                }
            }

            foreach ($intervalsByDay as $intervals) {
                usort($intervals, fn (array $left, array $right): int => $left[0] <=> $right[0]);

                for ($i = 1, $count = count($intervals); $i < $count; $i++) {
                    if ($intervals[$i][0] < $intervals[$i - 1][1]) {
                        throw ValidationException::withMessages([
                            "layers.{$index}.entries" => ['Windows in a layer must not overlap.'],
                        ]);
                    }
                }
            }
        }
    }

    /**
     * @param  list<string>  $userIds
     */
    private function assertUsersAreMembers(Team $team, array $userIds): void
    {
        $memberIds = $this->teamMemberIds($team);
        $unknown = array_values(array_diff($userIds, $memberIds));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'file' => ['Every on-call user must be a member of the team.'],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $layers
     * @param  list<int>|null  $layerDelays
     * @return list<array<string, mixed>>
     */
    private function applyLayerDelays(array $layers, ?array $layerDelays): array
    {
        foreach ($layers as $index => $layer) {
            if ($layerDelays !== null && array_key_exists($index, $layerDelays)) {
                $layers[$index]['escalateAfterMinutes'] = (int) $layerDelays[$index];

                continue;
            }

            $layers[$index]['escalateAfterMinutes'] = (int) ($layer['escalateAfterMinutes'] ?? self::DEFAULT_ESCALATE_AFTER_MINUTES);
        }

        return $layers;
    }

    /**
     * @param  list<array<string, mixed>>  $layers
     * @return list<string>
     */
    public function rosterUserIds(array $layers): array
    {
        $ids = [];

        foreach ($layers as $layer) {
            foreach ($layer['entries'] ?? [] as $entry) {
                $ids[] = (string) $entry['userId'];
            }
        }

        return array_values(array_unique($ids));
    }
}
