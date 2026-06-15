<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class TeamController extends Controller
{
    public function __construct(protected TeamService $teamService) {}

    public function Index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->perPage ?? 25);
        $user = auth()->user();

        $data = Team::query()->with(['owner']);

        if ($request->filled('name')) {
            $data->where('name', 'like', '%'.$request->name.'%');
        }

        $paginator = $data->paginate($perPage);

        foreach ($paginator as $team) {
            $this->teamService->applyTeamAccess($user, $team);
        }

        return response()->json($paginator);
    }

    public function All(): JsonResponse
    {
        $user = auth()->user();

        $teams = Team::query()
            ->with(['owner'])
            ->get();

        $teams->each(fn (Team $team) => $this->teamService->applyTeamAccess($user, $team));

        return response()->json($teams);
    }

    public function Show(string $id): JsonResponse
    {
        $team = Team::query()->with(['owner'])->where('_id', $id)->firstOrFail();

        return response()->json(
            $this->teamService->applyTeamAccess(auth()->user(), $team)
        );
    }

    public function Create(Request $request): JsonResponse
    {
        $validated = Validator::validate($request->all(), $this->teamRules());

        $team = Team::create([
            'name' => $validated['name'],
            'ownerId' => $validated['ownerId'],
            'userIds' => $validated['userIds'],
            'description' => $validated['description'] ?? '',
        ]);

        return response()->json([
            'status' => true,
            'data' => $this->teamService->applyTeamAccess(auth()->user(), $team->fresh(['owner'])),
        ]);
    }

    public function Update(Request $request, string $id): JsonResponse
    {
        $team = Team::query()->where('_id', $id)->firstOrFail();
        $user = auth()->user();

        if (! $this->teamService->canUpdateTeam($user, $team)) {
            abort(403);
        }

        $validated = Validator::validate(
            $request->all(),
            $this->teamRules($team->id),
        );

        $team->update([
            'name' => $validated['name'],
            'ownerId' => $validated['ownerId'],
            'userIds' => $validated['userIds'],
            'description' => $validated['description'] ?? '',
        ]);

        return response()->json([
            'status' => true,
            'data' => $this->teamService->applyTeamAccess($user, $team->fresh(['owner'])),
        ]);
    }

    public function Delete(Request $request, string $id): JsonResponse
    {
        $model = Team::query()->where('_id', $id)->firstOrFail();
        $model->delete();

        return response()->json(['status' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function teamRules(?string $ignoreId = null): array
    {
        $nameRule = ['required', 'string', 'max:255'];

        if ($ignoreId !== null) {
            $nameRule[] = Rule::unique('teams')->ignore($ignoreId, '_id');
        } else {
            $nameRule[] = Rule::unique('teams');
        }

        return [
            'name' => $nameRule,
            'ownerId' => 'required|string',
            'userIds' => 'required|array|min:1',
            'userIds.*' => 'required|string',
            'description' => 'nullable|string',
        ];
    }
}
