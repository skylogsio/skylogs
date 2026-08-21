<?php

namespace App\Http\Controllers\V1\Incident;

use App\Http\Requests\IncidentTimeline\IndexIncidentTimelineRequest;
use App\Http\Requests\IncidentTimeline\StoreIncidentTimelineEntryRequest;
use App\Http\Resources\IncidentTimeline\IncidentTimelineEntryResource;
use App\Http\Resources\PaginatedJson;
use App\Services\IncidentService;
use App\Services\IncidentTimelineService;
use Illuminate\Http\JsonResponse;

class TimelineController extends IncidentSubResourceController
{
    public function __construct(
        IncidentService $incidentService,
        private readonly IncidentTimelineService $timelineService,
    ) {
        parent::__construct($incidentService);
    }

    public function index(IndexIncidentTimelineRequest $request, string $incidentId): JsonResponse
    {
        $incident = $this->viewableIncident($incidentId);
        $perPage = (int) ($request->validated('perPage') ?? 50);

        $query = $this->timelineService->query($incident);

        if ($request->filled('type')) {
            $query->where('type', $request->validated('type'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->validated('source'));
        }

        if ($request->has('isPublic')) {
            $query->where('isPublic', $request->boolean('isPublic'));
        }

        return PaginatedJson::make(
            $query->orderBy('occurredAt')->paginate($perPage),
            IncidentTimelineEntryResource::class,
        );
    }

    public function store(StoreIncidentTimelineEntryRequest $request, string $incidentId): JsonResponse
    {
        $incident = $this->viewableIncident($incidentId);

        $entry = $this->timelineService->recordUserEntry(
            auth()->user(),
            $incident,
            $request->validated(),
        );

        return (new IncidentTimelineEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }
}
