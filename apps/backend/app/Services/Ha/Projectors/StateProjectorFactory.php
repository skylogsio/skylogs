<?php

namespace App\Services\Ha\Projectors;

use App\Enums\AlertRuleType;

final class StateProjectorFactory
{
    public function make(AlertRuleType $type): StateProjector
    {
        return match ($type) {
            AlertRuleType::API, AlertRuleType::NOTIFICATION => app(AlertInstanceProjector::class),
            AlertRuleType::PROMETHEUS => app(PrometheusProjector::class),
            AlertRuleType::GRAFANA, AlertRuleType::PMM => app(GrafanaProjector::class),
            AlertRuleType::ZABBIX => app(ZabbixProjector::class),
            AlertRuleType::ELASTIC => app(ElasticProjector::class),
            AlertRuleType::VICTORIA_LOGS => app(VictoriaLogsProjector::class),
            AlertRuleType::HEALTH => app(HealthProjector::class),
            AlertRuleType::SENTRY, AlertRuleType::METABASE => app(RuleStateProjector::class),
            AlertRuleType::SPLUNK => app(NullProjector::class),
        };
    }
}
