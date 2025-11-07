<?php

namespace App\Models;

use App\Observers\TeamObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(TeamObserver::class)]
class Team extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];
}
