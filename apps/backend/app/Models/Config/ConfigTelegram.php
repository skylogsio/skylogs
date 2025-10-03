<?php

namespace App\Models\Config;

use App\Models\BaseModel;
use App\Observers\ConfigTelegramObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(ConfigTelegramObserver::class)]
class ConfigTelegram extends BaseModel
{

    public $timestamps = true;

    protected $guarded = ['id', '_id',];

}
