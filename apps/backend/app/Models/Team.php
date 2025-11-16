<?php

namespace App\Models;

use App\Observers\TeamObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(TeamObserver::class)]
class Team extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'ownerId', '_id');
    }

    public function members()
    {
        return $this->hasMany(User::class, 'userIds', '_id');
    }
}
