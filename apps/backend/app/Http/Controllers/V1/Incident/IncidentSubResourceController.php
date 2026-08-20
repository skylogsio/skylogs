<?php

namespace App\Http\Controllers\V1\Incident;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Services\IncidentService;

/**
 * Base for everything documented against an incident.
 *
 * Sub-resources have no permissions of their own: reading one needs read access to the
 * incident and writing one needs write access to it, so both checks live here.
 */
abstract class IncidentSubResourceController extends Controller
{
    public function __construct(protected readonly IncidentService $incidentService) {}

    protected function viewableIncident(string $incidentId): Incident
    {
        $incident = Incident::query()->where('_id', $incidentId)->firstOrFail();

        if (! $this->incidentService->canView(auth()->user(), $incident)) {
            abort(403);
        }

        return $incident;
    }

    protected function editableIncident(string $incidentId): Incident
    {
        $incident = Incident::query()->where('_id', $incidentId)->firstOrFail();

        if (! $this->incidentService->canEdit(auth()->user(), $incident)) {
            abort(403);
        }

        return $incident;
    }
}
