<?php

namespace App\Models\Config;

use App\Models\BaseModel;
use App\Observers\ConfigEmailObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(ConfigEmailObserver::class)]
class ConfigEmail extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];
}
