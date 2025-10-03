<?php

namespace App\Models;

use App\Services\PrometheusInstanceService;

class PrometheusInstance extends BaseModel
{

    public $timestamps = true;
    public static $title = "Prometheus Instance";
    public static $KEY = "prometheus_instance";

    public const STATE_FIRING = "firing";

    public const DOWN = 1;
    public const UP = 2;


    protected $guarded = ['id', '_id',];

    public function isOk(){
        return PrometheusInstanceService::getHealthCheck($this);
    }

    public function getRulesUrl(){
        return $this->url . "/api/v1/rules";
    }
    public function getLabelsUrl(){
        return $this->url . "/api/v1/labels";
    }
    public function getLabelsValueUrl($label){
        return $this->url . "/api/v1/label/$label/values";
    }

    public function getAlertsUrl(){
        return $this->url . "/api/v1/alerts";
    }
    public function getQueryUrl($metric){
        return $this->url . "/api/v1/query?query=$metric";
    }

}
