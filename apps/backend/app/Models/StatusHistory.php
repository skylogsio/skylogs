<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class StatusHistory extends BaseModel
{

    public $timestamps = true;
    public static $title = "Status History";
    public static $KEY = "status_history";

    protected $guarded = ['id', '_id',];

}
