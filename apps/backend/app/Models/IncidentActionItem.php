<?php

namespace App\Models;

use App\Enums\IncidentActionItemCategory;
use App\Enums\IncidentActionItemPriority;
use App\Enums\IncidentActionItemStatus;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * Follow-up work an incident produced.
 *
 * Action items belong to an incident and optionally to its postmortem, so items agreed
 * during the response and items agreed during the review live in one collection and can
 * be listed together per incident or across incidents by owner.
 */
class IncidentActionItem extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected $attributes = [
        'description' => '',
        'status' => IncidentActionItemStatus::Open->value,
        'priority' => IncidentActionItemPriority::Medium->value,
        'category' => IncidentActionItemCategory::Other->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IncidentActionItemStatus::class,
            'priority' => IncidentActionItemPriority::class,
            'category' => IncidentActionItemCategory::class,
            'dueAt' => 'datetime',
            'completedAt' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incidentId', '_id');
    }

    public function postMortem(): BelongsTo
    {
        return $this->belongsTo(PostMortem::class, 'postMortemId', '_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ownerId', '_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'teamId', '_id');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            IncidentActionItemStatus::Done,
            IncidentActionItemStatus::Cancelled,
        ], true);
    }
}
