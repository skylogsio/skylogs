<?php

namespace App\Models\Config;

use App\Models\BaseModel;
use App\Observers\ConfigSkylogsObserver;
use App\Observers\ConfigTelegramObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(ConfigSkylogsObserver::class)]
class   ConfigSkylogs extends BaseModel
{

    public $timestamps = true;

    protected $guarded = ['id', '_id',];


}
