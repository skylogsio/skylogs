<?php

namespace App\Models\Config;

use App\Models\BaseModel;
use App\Observers\ConfigSkylogsObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(ConfigSkylogsObserver::class)]
class ConfigSkylogs extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];
}
