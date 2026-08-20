<?php

namespace App\Http\Controllers\V1\Incident;

use App\Http\Requests\PostMortem\UpdatePostMortemRequest;
use App\Http\Resources\PostMortem\PostMortemResource;
use App\Models\Incident;
use App\Models\PostMortem;
use App\Services\IncidentService;
use App\Services\PostMortemService;
use Illuminate\Http\JsonResponse;

class PostMortemController extends IncidentSubResourceController
{
    public function __construct(
        IncidentService $incidentService,
        private readonly PostMortemService $postMortemService,
    ) {
        parent::__construct($incidentService);
    }

    /**
     * Returns `data: null` while the incident has no postmortem yet, so the UI can show
     * an empty form without treating the absence as an error.
     */
    public function show(string $incidentId): JsonResponse
    {
        $incident = $this->viewableIncident($incidentId);
        $postMortem = $this->postMortemService->forIncident($incident);

        if ($postMortem === null) {
            return response()->json(['data' => null]);
        }

        return (new PostMortemResource($this->withAccessFlags($incident, $postMortem)))->response();
    }

    public function update(UpdatePostMortemRequest $request, string $incidentId): PostMortemResource
    {
        $incident = $this->editableIncident($incidentId);

        $postMortem = $this->postMortemService->upsert(
            auth()->user(),
            $incident,
            $request->validated(),
        );

        return new PostMortemResource($this->withAccessFlags($incident, $postMortem));
    }

    public function publish(string $incidentId): PostMortemResource
    {
        $incident = $this->editableIncident($incidentId);
        $postMortem = $this->postMortemService->forIncident($incident);

        if ($postMortem === null) {
            abort(404);
        }

        $postMortem = $this->postMortemService->publish(auth()->user(), $incident, $postMortem);

        return new PostMortemResource($this->withAccessFlags($incident, $postMortem));
    }

    private function withAccessFlags(Incident $incident, PostMortem $postMortem): PostMortem
    {
        $postMortem->setAttribute(
            'canEdit',
            $this->incidentService->canEdit(auth()->user(), $incident),
        );

        return $postMortem;
    }
}
