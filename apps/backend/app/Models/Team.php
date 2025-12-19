<?php

namespace App\Models;

use App\Observers\TeamObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(TeamObserver::class)]
class Team extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected $appends = ['members'];

    public function getMembersAttribute()
    {
        if (! empty($this->userIds)) {
            return User::whereIn('id', $this->userIds)->get()->pluck('name');
        }

        return [];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'ownerId', '_id');
    }
}
