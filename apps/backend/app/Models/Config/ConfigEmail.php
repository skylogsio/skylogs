<?php

namespace App\Models\Config;

use App\Models\BaseModel;

class ConfigEmail extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];
}
