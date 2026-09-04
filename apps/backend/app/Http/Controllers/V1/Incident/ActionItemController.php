<?php

namespace App\Http\Controllers\V1\Incident;

use App\Http\Requests\IncidentActionItem\IndexIncidentActionItemRequest;
use App\Http\Requests\IncidentActionItem\StoreIncidentActionItemRequest;
use App\Http\Requests\IncidentActionItem\UpdateIncidentActionItemRequest;
use App\Http\Resources\IncidentActionItem\IncidentActionItemResource;
use App\Http\Resources\PaginatedJson;
use App\Models\IncidentActionItem;
use App\Services\IncidentActionItemService;
use App\Services\IncidentService;
use Illuminate\Http\JsonResponse;

class ActionItemController extends IncidentSubResourceController
{
    public function __construct(
        IncidentService $incidentService,
        private readonly IncidentActionItemService $actionItemService,
    ) {
        parent::__construct($incidentService);
    }

    public function index(IndexIncidentActionItemRequest $request, string $incidentId): JsonResponse
    {
        $incident = $this->viewableIncident($incidentId);
        $perPage = (int) ($request->validated('perPage') ?? 50);
        $canEdit = $this->incidentService->canEdit(auth()->user(), $incident);

        $query = $this->actionItemService->query($incident);

        foreach (['status', 'priority', 'category', 'ownerId', 'teamId'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->validated($filter));
            }
        }

        $paginator = $query->orderBy('dueAt')->paginate($perPage);

        foreach ($paginator as $actionItem) {
            $actionItem->setAttribute('canEdit', $canEdit);
            $actionItem->setAttribute('canDelete', $canEdit);
        }

        return PaginatedJson::make($paginator, IncidentActionItemResource::class);
    }

    public function store(StoreIncidentActionItemRequest $request, string $incidentId): JsonResponse
    {
        $incident = $this->editableIncident($incidentId);

        $actionItem = $this->actionItemService->create(
            auth()->user(),
            $incident,
            $request->validated(),
        );

        return (new IncidentActionItemResource($this->withAccessFlags($actionItem)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateIncidentActionItemRequest $request,
        string $incidentId,
        string $actionItemId,
    ): IncidentActionItemResource {
        $incident = $this->editableIncident($incidentId);

        $actionItem = $this->actionItemService->update(
            $this->findActionItem($incident->id, $actionItemId),
            $request->validated(),
        );

        return new IncidentActionItemResource($this->withAccessFlags($actionItem));
    }

    public function destroy(string $incidentId, string $actionItemId): JsonResponse
    {
        $incident = $this->editableIncident($incidentId);

        $this->findActionItem($incident->id, $actionItemId)->delete();

        return response()->json(['status' => true]);
    }

    private function findActionItem(string $incidentId, string $actionItemId): IncidentActionItem
    {
        return IncidentActionItem::query()
            ->where('_id', $actionItemId)
            ->where('incidentId', $incidentId)
            ->firstOrFail();
    }

    private function withAccessFlags(IncidentActionItem $actionItem): IncidentActionItem
    {
        $actionItem->setAttribute('canEdit', true);
        $actionItem->setAttribute('canDelete', true);

        return $actionItem;
    }
}
