<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class TeamController extends Controller
{
    public function Index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->perPage ?? 25);

        $data = Team::query()->with(['owner']);

        if ($request->filled('name')) {
            $data->where('name', 'like', '%'.$request->name.'%');
        }

        return response()->json($data->paginate($perPage));
    }

    public function All(): JsonResponse
    {
        return response()->json(Team::all());
    }

    public function Show(string $id): JsonResponse
    {
        return response()->json(
            Team::query()->with(['owner'])->where('_id', $id)->firstOrFail()
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
            'data' => $team->fresh(['owner']),
        ]);
    }

    public function Update(Request $request, string $id): JsonResponse
    {
        $team = Team::query()->where('_id', $id)->firstOrFail();

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
            'data' => $team->fresh(['owner']),
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
