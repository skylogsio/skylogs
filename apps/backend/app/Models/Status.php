<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Status extends BaseModel
{

    public $timestamps = true;
    public static $title = "Status";
    public static $KEY = "status";

    protected $guarded = ['id', '_id',];

    public const ALERT_RULE = "ALERT_RULE";
    public const ALERT_CONTENT = "ALERT_CONTENT";




}
