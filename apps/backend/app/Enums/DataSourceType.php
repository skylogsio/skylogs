<?php

namespace App\Enums;

enum DataSourceType: string
{
    case PROMETHEUS = 'prometheus';
    case SENTRY = 'sentry';
    case GRAFANA = 'grafana';
    case PMM = 'pmm';
    case ZABBIX = 'zabbix';
    case SPLUNK = 'splunk';
    case ELASTIC = 'elastic';

    public static function GetTypes()
    {
        return [
            self::PROMETHEUS,
            self::SENTRY,
            self::GRAFANA,
            self::PMM,
            self::ZABBIX,
            self::SPLUNK,
            self::ELASTIC,
        ];
    }
}
