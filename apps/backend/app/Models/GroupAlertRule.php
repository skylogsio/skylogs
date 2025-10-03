<?php

namespace App\Models;

class GroupAlertRule extends BaseModel
{

    public $timestamps = true;
    public static $title = "Group";
    public static $KEY = "group";

    protected $guarded = ['id', '_id',];

//    protected $casts = [];

    public const STATUS_CREATED = 1;
    public const STATUS_RUNNING = 2;
    public const STATUS_DONE = 3;
    public const STATUS_FAIL = 4;
    public const STATUS_SILENT = 5;

}
