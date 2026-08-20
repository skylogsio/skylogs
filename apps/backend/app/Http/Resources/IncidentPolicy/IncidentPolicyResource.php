<?php

namespace App\Http\Resources\IncidentPolicy;

use App\Models\IncidentPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IncidentPolicy
 */
class IncidentPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'enabled' => $this->enabled,
            'version' => $this->version,
            'source' => $this->source,
            'ownerId' => $this->ownerId,
            'teamIds' => $this->teamIds ?? [],
            'teams' => $this->resolveTeams()->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
            ]),
            'match' => $this->match ?? [],
            'grouping' => $this->grouping ?? [],
            'incident' => $this->incident ?? [],
            'rules' => $this->rules ?? [],
            'createdBy' => $this->createdBy,
            'createdByUser' => $this->whenLoaded('createdByUser', fn () => [
                'id' => $this->createdByUser->id,
                'name' => $this->createdByUser->name,
            ]),
            'updatedBy' => $this->updatedBy,
            'canEdit' => $this->canEdit ?? false,
            'canDelete' => $this->canDelete ?? false,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
