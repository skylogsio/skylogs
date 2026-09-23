<?php

namespace App\Services\Health\DataSourceProbes;

use App\Enums\DataSourceType;
use App\Models\DataSource\DataSource;
use App\Services\Health\HealthProbe;

final class DataSourceProbeFactory
{
    public function for(DataSource $dataSource): HealthProbe
    {
        $builder = match ($dataSource->type) {
            DataSourceType::PROMETHEUS => app(PrometheusProbe::class),
            DataSourceType::GRAFANA => app(GrafanaProbe::class),
            DataSourceType::PMM => app(PmmProbe::class),
            DataSourceType::ZABBIX => app(ZabbixProbe::class),
            DataSourceType::ELASTIC => app(ElasticProbe::class),
            DataSourceType::SENTRY => app(SentryProbe::class),
            DataSourceType::VICTORIA_LOGS => app(VictoriaLogsProbe::class),
            DataSourceType::SPLUNK => app(SplunkProbe::class),
        };

        return $builder->build($dataSource);
    }
}
