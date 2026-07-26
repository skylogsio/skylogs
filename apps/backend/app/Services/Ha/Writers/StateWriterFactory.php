<?php

namespace App\Services\Ha\Writers;

use App\Enums\AlertRuleType;

final class StateWriterFactory
{
    public function make(AlertRuleType $type): StateWriter
    {
        return match ($type) {
            AlertRuleType::API, AlertRuleType::NOTIFICATION => app(AlertInstanceStateWriter::class),
            AlertRuleType::PROMETHEUS => app(PrometheusStateWriter::class),
            AlertRuleType::GRAFANA, AlertRuleType::PMM => app(GrafanaStateWriter::class),
            AlertRuleType::ZABBIX => app(ZabbixStateWriter::class),
            AlertRuleType::ELASTIC => app(ElasticStateWriter::class),
            AlertRuleType::VICTORIA_LOGS => app(VictoriaLogsStateWriter::class),
            AlertRuleType::HEALTH => app(HealthStateWriter::class),
            AlertRuleType::SENTRY, AlertRuleType::METABASE => app(RuleStateWriter::class),
            AlertRuleType::SPLUNK => app(NullStateWriter::class),
        };
    }
}
