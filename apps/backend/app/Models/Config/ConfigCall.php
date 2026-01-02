<?php

namespace App\Models\Config;

use App\Models\BaseModel;
use App\Observers\ConfigCallObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(ConfigCallObserver::class)]
class ConfigCall extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];
}
