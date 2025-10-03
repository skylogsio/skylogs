<?php

namespace App\Models;



class AlertRulePrometheus extends BaseModel
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
