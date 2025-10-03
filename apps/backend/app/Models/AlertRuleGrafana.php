<?php

namespace App\Models;

use App\Interfaces\Messageable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

class AlertRuleGrafana extends BaseModel
{

    public $timestamps = true;
    public static $title = "Alert Rule Prometheus";
    public static $KEY = "alerts";

    protected $guarded = ['id','_id',];


    public static $types = [
        "api" => "Api",
        "sentry" => "Sentry",
        "health" => "Health",
    ];


}
