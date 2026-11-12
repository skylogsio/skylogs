<?php

namespace App\Models;

use App\Enums\PostMortemStatus;
use Illuminate\Database\Eloquent\Collection;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

/**
 * The written record of one incident: what happened, why, and what changes because of it.
 *
 * At most one document exists per incident, so `incidentId` is unique. The root cause
 * analysis is embedded rather than a separate collection, because it is never read or
 * reviewed independently of the postmortem that contains it.
 *
 * @property array{
 *     method: string|null,
 *     whys: list<string>,
 *     contributingFactors: list<string>,
 *     statement: string|null
 * } $rootCause
 */
class PostMortem extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected $attributes = [
        'status' => PostMortemStatus::Draft->value,
        'summary' => '',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostMortemStatus::class,
            'dueAt' => 'datetime',
            'publishedAt' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incidentId', '_id');
    }

    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorId', '_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(IncidentActionItem::class, 'postMortemId', '_id');
    }

    public function isPublished(): bool
    {
        return $this->status === PostMortemStatus::Published;
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveReviewers(): Collection
    {
        if (empty($this->reviewerIds)) {
            return new Collection;
        }

        return User::query()->whereIn('_id', $this->reviewerIds)->get();
    }
}
