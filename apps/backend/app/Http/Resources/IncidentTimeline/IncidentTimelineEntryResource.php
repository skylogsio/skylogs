<?php

namespace App\Http\Resources\IncidentTimeline;

use App\Models\IncidentTimelineEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IncidentTimelineEntry
 */
class IncidentTimelineEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incidentId' => $this->incidentId,
            'type' => $this->type,
            'source' => $this->source,
            'occurredAt' => $this->occurredAt,
            'message' => $this->message,
            'meta' => $this->meta ?? [],
            'isPublic' => $this->isPublic,
            'userId' => $this->userId,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
