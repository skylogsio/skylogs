<?php

namespace App\Models\Config;

use App\Models\BaseModel;
use App\Observers\ConfigSmsObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(ConfigSmsObserver::class)]
class ConfigSms extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];
}
