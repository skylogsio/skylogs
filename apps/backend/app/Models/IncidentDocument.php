<?php

namespace App\Models;

use App\Enums\IncidentDocumentAttachableType;
use App\Enums\IncidentDocumentType;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * An uploaded file or an external link attached to an incident or its postmortem.
 *
 * `attachableType` plus `attachableId` give the owning document, while `incidentId` is
 * denormalised onto every row so that listing and authorising by incident stays a single
 * indexed read even for documents that hang off a postmortem.
 *
 * Uploads carry `disk`, `path`, `fileName`, `mimeType` and `size`; links carry
 * `externalUrl` instead. Exactly one of the two is ever set.
 */
class IncidentDocument extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected $attributes = [
        'type' => IncidentDocumentType::Other->value,
        'attachableType' => IncidentDocumentAttachableType::Incident->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachableType' => IncidentDocumentAttachableType::class,
            'type' => IncidentDocumentType::class,
            'size' => 'integer',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incidentId', '_id');
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploadedBy', '_id');
    }

    public function isExternalLink(): bool
    {
        return $this->path === null;
    }
}
