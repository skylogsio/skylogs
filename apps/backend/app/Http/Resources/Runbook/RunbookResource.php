<?php

namespace App\Http\Resources\Runbook;

use App\Models\Runbook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Runbook
 */
class RunbookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'version' => $this->version,
            'sourceType' => $this->sourceType,
            'content' => $this->content,
            'externalUrl' => $this->externalUrl,
            'steps' => $this->steps ?? [],
            'teamIds' => $this->teamIds ?? [],
            'teams' => $this->resolveTeams()->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
            ]),
            'tags' => $this->tags ?? [],
            'appliesTo' => $this->appliesTo ?? [],
            'reviewIntervalDays' => $this->reviewIntervalDays,
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
