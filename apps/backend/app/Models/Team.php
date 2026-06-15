<?php

namespace App\Models;

use App\Observers\TeamObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use MongoDB\Laravel\Relations\BelongsTo;

#[ObservedBy(TeamObserver::class)]
class Team extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected $appends = ['members'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ownerId', '_id');
    }

    /**
     * @return list<string>
     */
    public function getMembersAttribute(): array
    {
        if (empty($this->userIds)) {
            return [];
        }

        return $this->resolveMemberUsers()->pluck('name')->values()->all();
    }

    public function resolveMemberUsers(): Collection
    {
        return User::query()
            ->whereIn('_id', $this->userIds)
            ->get();
    }
}
