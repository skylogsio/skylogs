<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\OnCallPlan\CurrentOnCallPlanRequest;
use App\Http\Requests\OnCallPlan\OnCallPlanAtRequest;
use App\Http\Requests\OnCallPlan\OnCallPlanDefinitionRequest;
use App\Http\Requests\OnCallPlan\StoreOnCallPlanRequest;
use App\Http\Requests\OnCallPlan\UpdateOnCallPlanRequest;
use App\Http\Resources\OnCallPlan\OnCallPlanResource;
use App\Models\Team;
use App\Models\User;
use App\Services\OnCallPlanExcelImporter;
use App\Services\OnCallPlanService;
use App\Services\OnCallResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class OnCallPlanController extends Controller
{
    public function __construct(
        private readonly OnCallPlanService $onCallPlanService,
        private readonly OnCallResolver $resolver,
        private readonly OnCallPlanExcelImporter $importer,
    ) {}

    public function show(string $teamId): OnCallPlanResource
    {
        [$team, $user] = $this->viewableTeam($teamId);
        $plan = $this->onCallPlanService->findForTeam($team);

        if ($plan === null) {
            abort(404);
        }

        return new OnCallPlanResource(
            $this->onCallPlanService->applyAccessFlags($user, $plan, $team),
        );
    }

    public function store(StoreOnCallPlanRequest $request, string $teamId): JsonResponse
    {
        [$team, $user] = $this->editableTeam($teamId);
        $layers = $this->parsedLayers($request, $team);

        return (new OnCallPlanResource(
            $this->onCallPlanService->create($user, $team, $request->validated(), $layers),
        ))->response()->setStatusCode(201);
    }

    public function update(UpdateOnCallPlanRequest $request, string $teamId): OnCallPlanResource
    {
        [$team, $user] = $this->editableTeam($teamId);
        $plan = $this->onCallPlanService->findForTeam($team);

        if ($plan === null) {
            abort(404);
        }

        $layers = $this->parsedLayers($request, $team);

        return new OnCallPlanResource(
            $this->onCallPlanService->update($user, $team, $plan, $request->validated(), $layers),
        );
    }

    public function destroy(string $teamId): JsonResponse
    {
        [$team] = $this->editableTeam($teamId);
        $plan = $this->onCallPlanService->findForTeam($team);

        if ($plan === null) {
            abort(404);
        }

        $plan->delete();

        return response()->json(['status' => true]);
    }

    public function at(OnCallPlanAtRequest $request, string $teamId): JsonResponse
    {
        [$team] = $this->viewableTeam($teamId);
        $plan = $this->onCallPlanService->findForTeam($team);

        if ($plan === null) {
            abort(404);
        }

        $at = $request->validated('at');

        return response()->json(
            $this->resolver->at($plan, $at === null ? null : Carbon::parse($at)),
        );
    }

    public function current(CurrentOnCallPlanRequest $request): JsonResponse
    {
        $user = auth()->user();
        $visible = $this->onCallPlanService->visibleTeams($user);
        $teams = $this->onCallPlanService->teamsForCurrentLookup(
            $user,
            $visible,
            $request->validated('teamIds') ?? null,
        );
        $at = $request->validated('at');

        return response()->json([
            'data' => $this->onCallPlanService->currentForTeams(
                $teams,
                $at === null ? null : Carbon::parse($at),
            ),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsedLayers(OnCallPlanDefinitionRequest $request, Team $team): array
    {
        $parsed = $this->importer->parse($request->file('file'), $team);

        if ($parsed['errors'] !== []) {
            abort(response()->json([
                'message' => 'The on-call roster could not be imported.',
                'errors' => $parsed['errors'],
            ], 422));
        }

        return $parsed['layers'];
    }

    /**
     * @return array{0: Team, 1: User}
     */
    private function viewableTeam(string $teamId): array
    {
        $team = Team::query()->where('_id', $teamId)->firstOrFail();
        $user = auth()->user();

        if (! $this->onCallPlanService->canView($user, $team)) {
            abort(403);
        }

        return [$team, $user];
    }

    /**
     * @return array{0: Team, 1: User}
     */
    private function editableTeam(string $teamId): array
    {
        $team = Team::query()->where('_id', $teamId)->firstOrFail();
        $user = auth()->user();

        if (! $this->onCallPlanService->canEdit($user, $team)) {
            abort(403);
        }

        return [$team, $user];
    }
}
