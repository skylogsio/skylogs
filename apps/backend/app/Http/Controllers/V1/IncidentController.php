<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\AcknowledgeIncidentRequest;
use App\Http\Requests\Incident\IndexIncidentRequest;
use App\Http\Requests\Incident\ResolveIncidentRequest;
use App\Http\Requests\Incident\StoreIncidentRequest;
use App\Http\Requests\Incident\UpdateIncidentRequest;
use App\Http\Resources\Incident\IncidentResource;
use App\Http\Resources\PaginatedJson;
use App\Models\Incident;
use App\Services\IncidentService;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;

class IncidentController extends Controller
{
    public function __construct(private readonly IncidentService $incidentService) {}

    public function index(IndexIncidentRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('perPage') ?? 25);
        $user = auth()->user();

        $query = Incident::query()->with(['createdByUser', 'postMortem']);

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->validated('severity'));
        }

        if ($request->filled('teamId')) {
            $query->where('teamIds', $request->validated('teamId'));
        }

        if ($request->filled('tag')) {
            $query->where('tags', $request->validated('tag'));
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->validated('search').'%');
        }

        if (! $user->isAdmin()) {
            $userTeamIds = array_map(
                'strval',
                app(TeamService::class)->userTeams($user)->pluck('_id')->all(),
            );

            $query->where(function ($q) use ($user, $userTeamIds) {
                $q->where('createdBy', (string) $user->id);

                foreach ($userTeamIds as $teamId) {
                    $q->orWhere('teamIds', $teamId);
                }
            });
        }

        $query->orderByDesc('startedAt');

        $paginator = $query->paginate($perPage);

        foreach ($paginator as $incident) {
            $this->incidentService->applyAccessFlags($user, $incident);
        }

        return PaginatedJson::make($paginator, IncidentResource::class);
    }

    public function store(StoreIncidentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = auth()->user();

        if (! $this->incidentService->canCreate($user, $validated['teamIds'])) {
            abort(403);
        }

        $incident = $this->incidentService->create($user, $validated, $request->documentFiles());

        return (new IncidentResource($incident))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): IncidentResource
    {
        $incident = Incident::query()
            ->with(['createdByUser', 'postMortem'])
            ->where('_id', $id)
            ->firstOrFail();
        $user = auth()->user();

        if (! $this->incidentService->canView($user, $incident)) {
            abort(403);
        }

        $this->incidentService->applyAccessFlags($user, $incident);
        $incident->setAttribute('counts', $this->incidentService->documentationCounts($incident));

        return new IncidentResource($incident);
    }

    public function update(UpdateIncidentRequest $request, string $id): IncidentResource
    {
        $incident = Incident::query()->where('_id', $id)->firstOrFail();
        $user = auth()->user();

        if (! $this->incidentService->canEdit($user, $incident)) {
            abort(403);
        }

        $validated = $request->validated();

        if (! $this->incidentService->canCreate($user, $validated['teamIds'])) {
            abort(403);
        }

        $incident = $this->incidentService->update($user, $incident, $validated, $request->documentFiles());

        return new IncidentResource($incident);
    }

    public function destroy(string $id): JsonResponse
    {
        $incident = Incident::query()->where('_id', $id)->firstOrFail();
        $user = auth()->user();

        if (! $this->incidentService->canEdit($user, $incident)) {
            abort(403);
        }

        $this->incidentService->delete($incident);

        return response()->json(['status' => true]);
    }

    public function acknowledge(AcknowledgeIncidentRequest $request, string $id): IncidentResource
    {
        $incident = Incident::query()->where('_id', $id)->firstOrFail();
        $user = auth()->user();

        if (! $this->incidentService->canAcknowledge($user, $incident)) {
            abort(403);
        }

        $incident = $this->incidentService->acknowledge(
            $user,
            $incident,
            $request->validated('teamId'),
        );

        return new IncidentResource($incident);
    }

    public function resolve(ResolveIncidentRequest $request, string $id): IncidentResource
    {
        $incident = Incident::query()->where('_id', $id)->firstOrFail();
        $user = auth()->user();

        if (! $this->incidentService->canResolve($user, $incident)) {
            abort(403);
        }

        $incident = $this->incidentService->resolve(
            $user,
            $incident,
            $request->validated('resolvedAt'),
        );

        return new IncidentResource($incident);
    }
}
