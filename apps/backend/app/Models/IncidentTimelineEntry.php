<?php

namespace App\Models;

use App\Enums\IncidentTimelineEntrySource;
use App\Enums\IncidentTimelineEntryType;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * One dated fact about an incident, ordered by `occurredAt` rather than insertion time
 * so that a responder can backfill something that happened before it was written down.
 *
 * `isPublic` marks entries that may be shown outside the responding teams, for example
 * on a status page. System entries are written by `IncidentService`, never by clients.
 */
class IncidentTimelineEntry extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected $attributes = [
        'source' => IncidentTimelineEntrySource::User->value,
        'isPublic' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IncidentTimelineEntryType::class,
            'source' => IncidentTimelineEntrySource::class,
            'occurredAt' => 'datetime',
            'isPublic' => 'boolean',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incidentId', '_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId', '_id');
    }
}
