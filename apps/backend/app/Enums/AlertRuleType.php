<?php

namespace App\Enums;


enum AlertRuleType: string
{
    case API = "api";
    case NOTIFICATION = "notification";
    case PROMETHEUS = "prometheus";
    case SENTRY = "sentry";
    case METABASE = "metabase";
    case GRAFANA = "grafana";
    case PMM = "pmm";
    case ZABBIX = "zabbix";
    case SPLUNK = "splunk";
    case ELASTIC = "elastic";
    case HEALTH = "health";

    public static function GetTypes()
    {
        return [
            self::API,
            self::NOTIFICATION,
            self::PROMETHEUS,
            self::SENTRY,
            self::GRAFANA,
            self::PMM,
            self::ZABBIX,
            self::SPLUNK,
            self::ELASTIC,
            self::HEALTH,
            self::METABASE,
        ];
    }

    public static function GetDataSourceAlertNeed()
    {
        return collect([
//            self::PROMETHEUS,
//            self::GRAFANA,
//            self::PMM,
            self::SPLUNK->value,
            self::SENTRY->value,
            self::METABASE->value,
        ]);
    }
}
