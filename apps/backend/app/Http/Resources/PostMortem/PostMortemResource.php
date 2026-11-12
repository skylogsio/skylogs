<?php

namespace App\Http\Resources\PostMortem;

use App\Models\PostMortem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PostMortem
 */
class PostMortemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incidentId' => $this->incidentId,
            'status' => $this->status,
            'summary' => $this->summary,
            'impact' => $this->impact,
            'detection' => $this->detection,
            'resolution' => $this->resolution,
            'rootCause' => $this->rootCause ?? [
                'method' => null,
                'whys' => [],
                'contributingFactors' => [],
                'statement' => null,
            ],
            'whatWentWell' => $this->whatWentWell ?? [],
            'whatWentWrong' => $this->whatWentWrong ?? [],
            'lessonsLearned' => $this->lessonsLearned ?? [],
            'authorId' => $this->authorId,
            'authorUser' => $this->whenLoaded('authorUser', fn () => $this->authorUser === null ? null : [
                'id' => $this->authorUser->id,
                'name' => $this->authorUser->name,
            ]),
            'reviewerIds' => $this->reviewerIds ?? [],
            'dueAt' => $this->dueAt,
            'publishedAt' => $this->publishedAt,
            'canEdit' => $this->canEdit ?? false,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
