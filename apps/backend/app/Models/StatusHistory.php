<?php

namespace App\Models;

class StatusHistory extends BaseModel
{
    public $timestamps = true;

    public static $title = 'Status History';

    public static $KEY = 'status_history';

    protected $guarded = ['id', '_id'];
}
