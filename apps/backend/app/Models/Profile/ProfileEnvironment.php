<?php

namespace App\Models\Profile;

use App\Models\BaseModel;

class ProfileEnvironment extends BaseModel
{
    const TITLE = 'environment';

    public $timestamps = true;

    protected $guarded = ['id', '_id'];
}
