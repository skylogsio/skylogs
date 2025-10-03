<?php

namespace App\Models;

use App\Services\PrometheusInstanceService;
use MongoDB\Laravel\Relations\BelongsTo;

class Service extends BaseModel
{

    public $timestamps = true;
    public static $title = "ZabbixService";
    public static $KEY = "service";

    public const TYPE_PROMETHEUS = "prometheus";
    public const TYPE_SENTRY = "sentry";
    public const TYPE_GRAFANA = "grafana";
    public const TYPE_PMM = "pmm";
    public const TYPE_ZABBIX = "zabbix";
    public const TYPE_ELASTIC = "elastic";


    public const STATE_FIRING = "firing";

    public const DOWN = 1;
    public const UP = 2;


    protected $guarded = ['id', '_id',];


    public static $types = [
        self::TYPE_PROMETHEUS => "Prometheus",
        self::TYPE_SENTRY => "Sentry",
        self::TYPE_GRAFANA => "Grafana",
        self::TYPE_PMM => "Pmm",
        self::TYPE_ZABBIX => "Zabbix",
        self::TYPE_ELASTIC => "Elastic",
    ];


    public function getHealthUrl()
    {
        switch ($this->type) {
            case Service::TYPE_SENTRY:
                return $this->url . "/api/0";
            case Service::TYPE_ZABBIX:
                return $this->url . "/api_jsonrpc.php";
            case Service::TYPE_PROMETHEUS:
                return $this->url . "/api/v1/alerts";
            case Service::TYPE_PMM:
                return $this->url . "/grafana/api/v1/provisioning/alert-rules";
            case Service::TYPE_ELASTIC:
//                return $this->url."/api/0";
                break;
            case Service::TYPE_GRAFANA:
                return $this->url . "/api/v1/provisioning/alert-rules";
        }
    }


    public function alertsUrl()
    {
        switch ($this->type) {
            case Service::TYPE_SENTRY:
                return $this->url . "/api/0/organizations/sentry/alert-rules";
            case Service::TYPE_ZABBIX:
                return $this->url . "/api_jsonrpc.php";
            case Service::TYPE_PROMETHEUS:
                return $this->url . "/api/v1/alerts";
            case Service::TYPE_PMM:
                return $this->url . "/grafana/api/v1/provisioning/alert-rules";
            case Service::TYPE_ELASTIC:
//                return $this->url."/api/0";
                break;
            case Service::TYPE_GRAFANA:
                return $this->url . "/api/v1/provisioning/alert-rules";
        }
    }

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, "_id", "service_id");
    }


    public function isOk()
    {
        if($this->alertRule->getStatus() == AlertRule::RESOlVED){
            return true;
        }else{
            return false;
        }

    }
    public function isPrometheusOk()
    {
        return PrometheusInstanceService::getHealthCheck($this);
    }

    /**
     * @throws \Throwable
     */
    public function getPrometheusRulesUrl()
    {
        throw_if($this->type != self::TYPE_PROMETHEUS,);
        return $this->url . "/api/v1/rules";
    }

    /**
     * @throws \Throwable
     */
    public function getPrometheusLabelsUrl()
    {
        throw_if($this->type != self::TYPE_PROMETHEUS,);
        return $this->url . "/api/v1/labels";
    }

    /**
     * @throws \Throwable
     */
    public function getPrometheusLabelsValueUrl($label)
    {
        throw_if($this->type != self::TYPE_PROMETHEUS,);
        return $this->url . "/api/v1/label/$label/values";
    }

    /**
     * @throws \Throwable
     */
    public function getPrometheusAlertsUrl()
    {
        throw_if($this->type != self::TYPE_PROMETHEUS,);
        return $this->url . "/api/v1/alerts";
    }

    /**
     * @throws \Throwable
     */
    public function getPrometheusQueryUrl($metric)
    {
        throw_if($this->type != self::TYPE_PROMETHEUS,);
        return $this->url . "/api/v1/query?query=$metric";
    }

}
