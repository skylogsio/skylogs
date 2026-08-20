<?php

namespace App\Http\Resources\IncidentActionItem;

use App\Models\IncidentActionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IncidentActionItem
 */
class IncidentActionItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incidentId' => $this->incidentId,
            'incident' => $this->whenLoaded('incident', fn () => $this->incident === null ? null : [
                'id' => $this->incident->id,
                'title' => $this->incident->title,
                'severity' => $this->incident->severity,
                'status' => $this->incident->status,
            ]),
            'postMortemId' => $this->postMortemId,
            'title' => $this->title,
            'description' => $this->description,
            'ownerId' => $this->ownerId,
            'ownerUser' => $this->whenLoaded('ownerUser', fn () => $this->ownerUser === null ? null : [
                'id' => $this->ownerUser->id,
                'name' => $this->ownerUser->name,
            ]),
            'teamId' => $this->teamId,
            'priority' => $this->priority,
            'category' => $this->category,
            'status' => $this->status,
            'dueAt' => $this->dueAt,
            'completedAt' => $this->completedAt,
            'createdBy' => $this->createdBy,
            'canEdit' => $this->canEdit ?? false,
            'canDelete' => $this->canDelete ?? false,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
