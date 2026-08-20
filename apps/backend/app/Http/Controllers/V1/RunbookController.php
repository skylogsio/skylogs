<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Runbook\IndexRunbookRequest;
use App\Http\Requests\Runbook\StoreRunbookRequest;
use App\Http\Requests\Runbook\UpdateRunbookRequest;
use App\Http\Resources\Runbook\RunbookResource;
use App\Models\Runbook;
use App\Services\RunbookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RunbookController extends Controller
{
    public function __construct(private readonly RunbookService $runbookService) {}

    public function index(IndexRunbookRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->validated('perPage') ?? 25);
        $user = auth()->user();

        $query = Runbook::query()->with('createdByUser');

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        if ($request->filled('teamId')) {
            $query->where('teamIds', $request->validated('teamId'));
        }

        if ($request->filled('tag')) {
            $query->where('tags', $request->validated('tag'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->validated('search').'%');
        }

        $this->runbookService->applyVisibility($query, $user);

        $paginator = $query->orderBy('name')->paginate($perPage);

        foreach ($paginator as $runbook) {
            $this->runbookService->applyAccessFlags($user, $runbook);
        }

        return RunbookResource::collection($paginator);
    }

    public function show(string $id): RunbookResource
    {
        $runbook = Runbook::query()->with('createdByUser')->where('_id', $id)->firstOrFail();
        $user = auth()->user();

        if (! $this->runbookService->canView($user, $runbook)) {
            abort(403);
        }

        $this->runbookService->applyAccessFlags($user, $runbook);

        return new RunbookResource($runbook);
    }

    public function store(StoreRunbookRequest $request): JsonResponse
    {
        $runbook = $this->runbookService->create(auth()->user(), $request->validated());

        return (new RunbookResource($runbook))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateRunbookRequest $request, string $id): RunbookResource
    {
        $runbook = Runbook::query()->where('_id', $id)->firstOrFail();
        $user = auth()->user();

        if (! $this->runbookService->canEdit($user, $runbook)) {
            abort(403);
        }

        return new RunbookResource(
            $this->runbookService->update($user, $runbook, $request->validated()),
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $runbook = Runbook::query()->where('_id', $id)->firstOrFail();

        if (! $this->runbookService->canEdit(auth()->user(), $runbook)) {
            abort(403);
        }

        $runbook->delete();

        return response()->json(['status' => true]);
    }
}
