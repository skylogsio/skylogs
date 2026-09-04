<?php

namespace App\Http\Resources\IncidentDocument;

use App\Models\IncidentDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IncidentDocument
 */
class IncidentDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incidentId' => $this->incidentId,
            'attachableType' => $this->attachableType,
            'attachableId' => $this->attachableId,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'fileName' => $this->fileName,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'externalUrl' => $this->externalUrl,
            'isExternalLink' => $this->isExternalLink(),
            'uploadedBy' => $this->uploadedBy,
            'uploadedByUser' => $this->whenLoaded('uploadedByUser', fn () => $this->uploadedByUser === null ? null : [
                'id' => $this->uploadedByUser->id,
                'name' => $this->uploadedByUser->name,
            ]),
            'canDelete' => $this->canDelete ?? false,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
