<?php

namespace App\Models;

use App\Enums\RunbookSourceType;
use App\Enums\RunbookStatus;
use Illuminate\Database\Eloquent\Collection;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * Operational procedure a responder follows during an incident.
 *
 * A runbook carries its body in exactly one of three shapes, chosen by `sourceType`:
 * ordered `steps`, a `content` markdown blob, or an `externalUrl` pointing at a wiki.
 *
 * @property list<array{title: string, description: string|null, command: string|null, expectedResult: string|null}> $steps
 * @property array{
 *     serviceIds: list<string>,
 *     alertRuleIds: list<string>,
 *     tags: list<string>,
 *     severities: list<string>
 * } $appliesTo
 */
class Runbook extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected $attributes = [
        'description' => '',
        'status' => RunbookStatus::Draft->value,
        'sourceType' => RunbookSourceType::Steps->value,
        'version' => 1,
    ];

    /**
     * `teamIds`, `tags`, `steps` and `appliesTo` are deliberately not cast to `array`:
     * that cast JSON-encodes the value in MongoDB, which would defeat the indexes on
     * `teamIds` and `appliesTo.*`.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunbookStatus::class,
            'sourceType' => RunbookSourceType::class,
            'version' => 'integer',
            'reviewIntervalDays' => 'integer',
        ];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdBy', '_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updatedBy', '_id');
    }

    /**
     * @return Collection<int, Team>
     */
    public function resolveTeams(): Collection
    {
        if (empty($this->teamIds)) {
            return new Collection;
        }

        return Team::query()->whereIn('_id', $this->teamIds)->get();
    }
}
