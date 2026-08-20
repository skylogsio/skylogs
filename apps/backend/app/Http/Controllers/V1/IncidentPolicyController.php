<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncidentPolicy\ImportIncidentPolicyRequest;
use App\Http\Requests\IncidentPolicy\IndexIncidentPolicyRequest;
use App\Http\Requests\IncidentPolicy\StoreIncidentPolicyRequest;
use App\Http\Requests\IncidentPolicy\UpdateIncidentPolicyRequest;
use App\Http\Resources\IncidentPolicy\IncidentPolicyResource;
use App\Models\IncidentPolicy;
use App\Services\IncidentPolicy\IncidentPolicyExporter;
use App\Services\IncidentPolicy\IncidentPolicyImporter;
use App\Services\IncidentPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class IncidentPolicyController extends Controller
{
    public function __construct(
        private readonly IncidentPolicyService $incidentPolicyService,
        private readonly IncidentPolicyImporter $importer,
        private readonly IncidentPolicyExporter $exporter,
    ) {}

    public function index(IndexIncidentPolicyRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->validated('perPage') ?? 25);
        $user = auth()->user();

        $query = IncidentPolicy::query()->with('createdByUser');

        if ($request->has('enabled')) {
            $query->where('enabled', $request->boolean('enabled'));
        }

        if ($request->filled('teamId')) {
            $query->where('teamIds', $request->validated('teamId'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->validated('search').'%');
        }

        $this->incidentPolicyService->applyVisibility($query, $user);

        $paginator = $query->orderBy('name')->paginate($perPage);

        foreach ($paginator as $policy) {
            $this->incidentPolicyService->applyAccessFlags($user, $policy);
        }

        return IncidentPolicyResource::collection($paginator);
    }

    public function show(string $id): IncidentPolicyResource
    {
        $policy = IncidentPolicy::query()->with('createdByUser')->where('_id', $id)->firstOrFail();
        $user = auth()->user();

        if (! $this->incidentPolicyService->canView($user, $policy)) {
            abort(403);
        }

        $this->incidentPolicyService->applyAccessFlags($user, $policy);

        return new IncidentPolicyResource($policy);
    }

    public function store(StoreIncidentPolicyRequest $request): JsonResponse
    {
        $policy = $this->incidentPolicyService->create(auth()->user(), $request->validated());

        return (new IncidentPolicyResource($policy))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateIncidentPolicyRequest $request, string $id): IncidentPolicyResource
    {
        $policy = IncidentPolicy::query()->where('_id', $id)->firstOrFail();
        $user = auth()->user();

        if (! $this->incidentPolicyService->canEdit($user, $policy)) {
            abort(403);
        }

        return new IncidentPolicyResource(
            $this->incidentPolicyService->update($user, $policy, $request->validated()),
        );
    }

    /**
     * Applies a YAML definition. Idempotent by policy name.
     */
    public function import(ImportIncidentPolicyRequest $request): JsonResponse
    {
        $result = $this->importer->import(
            auth()->user(),
            $request->definition(),
            $request->isDryRun(),
        );

        return response()->json($result->toArray(), $result->isValid() ? 200 : 422);
    }

    /**
     * Same checks as import, but never writes.
     */
    public function validateImport(ImportIncidentPolicyRequest $request): JsonResponse
    {
        $result = $this->importer->import(auth()->user(), $request->definition(), dryRun: true);

        return response()->json($result->toArray(), $result->isValid() ? 200 : 422);
    }

    public function export(string $id): Response
    {
        $policy = IncidentPolicy::query()->where('_id', $id)->firstOrFail();

        if (! $this->incidentPolicyService->canView(auth()->user(), $policy)) {
            abort(403);
        }

        return response($this->exporter->export($policy), 200, [
            'Content-Type' => 'application/x-yaml',
            'Content-Disposition' => 'attachment; filename="'.$policy->name.'.yaml"',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $policy = IncidentPolicy::query()->where('_id', $id)->firstOrFail();

        if (! $this->incidentPolicyService->canEdit(auth()->user(), $policy)) {
            abort(403);
        }

        $policy->delete();

        return response()->json(['status' => true]);
    }
}
