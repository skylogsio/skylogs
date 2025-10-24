<?php

namespace App\Models\Profile;

use App\Models\BaseModel;

class ProfileService extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];
}
