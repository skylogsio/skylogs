<?php

namespace App\Models;

use MongoDB\Laravel\Relations\BelongsTo;

/**
 * Scaffold for later on-call CRUD. Layers map time windows to users and endpoints.
 *
 * @property list<array{
 *     level: int,
 *     escalateAfterMinutes: int,
 *     entries: list<array{
 *         userId: string,
 *         endpointIds: list<string>,
 *         windows: list<array{daysOfWeek: list<int>, startTime: string, endTime: string}>
 *     }>
 * }> $layers
 */
class OnCallPlan extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layers' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'teamId', '_id');
    }
}
