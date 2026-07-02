<?php

namespace App\Services\AlertStatus;

use App\Enums\AlertRuleType;
use App\Services\AlertStatus\Contracts\AlertStatusEventSource;
use App\Services\AlertStatus\Sources\ApiStatusEventSource;
use App\Services\AlertStatus\Sources\ElasticStatusEventSource;
use App\Services\AlertStatus\Sources\GrafanaStatusEventSource;
use App\Services\AlertStatus\Sources\HealthStatusEventSource;
use App\Services\AlertStatus\Sources\NullStatusEventSource;
use App\Services\AlertStatus\Sources\PrometheusStatusEventSource;
use App\Services\AlertStatus\Sources\SentryStatusEventSource;
use App\Services\AlertStatus\Sources\VictoriaLogsStatusEventSource;
use App\Services\AlertStatus\Sources\ZabbixStatusEventSource;

final class AlertStatusEventSourceFactory
{
    public function make(AlertRuleType $type): AlertStatusEventSource
    {
        return match ($type) {
            AlertRuleType::API => new ApiStatusEventSource,
            AlertRuleType::PROMETHEUS => new PrometheusStatusEventSource,
            AlertRuleType::GRAFANA, AlertRuleType::PMM => new GrafanaStatusEventSource,
            AlertRuleType::ZABBIX => new ZabbixStatusEventSource,
            AlertRuleType::SENTRY => new SentryStatusEventSource,
            AlertRuleType::ELASTIC => new ElasticStatusEventSource,
            AlertRuleType::VICTORIA_LOGS => new VictoriaLogsStatusEventSource,
            AlertRuleType::HEALTH => new HealthStatusEventSource,
            AlertRuleType::NOTIFICATION, AlertRuleType::METABASE, AlertRuleType::SPLUNK => new NullStatusEventSource,
        };
    }
}
