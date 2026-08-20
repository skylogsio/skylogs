<?php

namespace App\Http\Controllers\V1;

use App\Enums\IncidentActionItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IncidentActionItem\IndexIncidentActionItemRequest;
use App\Http\Resources\IncidentActionItem\IncidentActionItemResource;
use App\Services\IncidentActionItemService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Cross-incident view of follow-up work, for an "assigned to me" or overdue backlog.
 * Writing an action item always happens through its incident.
 */
class IncidentActionItemController extends Controller
{
    public function __construct(private readonly IncidentActionItemService $actionItemService) {}

    public function index(IndexIncidentActionItemRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->validated('perPage') ?? 25);
        $query = $this->actionItemService->query();

        foreach (['status', 'priority', 'category', 'ownerId', 'teamId', 'incidentId'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->validated($filter));
            }
        }

        if ($request->has('open') && $request->boolean('open')) {
            $query->whereIn('status', [
                IncidentActionItemStatus::Open->value,
                IncidentActionItemStatus::InProgress->value,
                IncidentActionItemStatus::Blocked->value,
            ]);
        }

        if ($request->has('overdue') && $request->boolean('overdue')) {
            $query->where('dueAt', '<', now())
                ->whereNotIn('status', [
                    IncidentActionItemStatus::Done->value,
                    IncidentActionItemStatus::Cancelled->value,
                ]);
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->validated('search').'%');
        }

        $this->actionItemService->applyVisibility($query, auth()->user());

        return IncidentActionItemResource::collection(
            $query->orderBy('dueAt')->paginate($perPage),
        );
    }
}
