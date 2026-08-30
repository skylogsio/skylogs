<?php

namespace App\Models;

use MongoDB\Laravel\Relations\BelongsTo;

/**
 * Team-owned weekly roster. One document per team. The Excel file is the roster;
 * each user's on-call endpoint lives on the Endpoint document (`onCall`).
 *
 * @property string $teamId
 * @property string $name
 * @property string $timezone
 * @property list<array{
 *     level: int,
 *     escalateAfterMinutes: int,
 *     entries: list<array{
 *         userId: string,
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
