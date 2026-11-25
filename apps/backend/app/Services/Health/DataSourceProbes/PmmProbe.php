<?php

namespace App\Services\Health\DataSourceProbes;

use App\Models\DataSource\DataSource;
use App\Services\Health\HealthProbe;

final class PmmProbe implements DataSourceProbeBuilder
{
    /**
     * Status check. Health alerts use {@see build()}.
     */
    public function connection(DataSource $dataSource): HealthProbe
    {
        return new HealthProbe(
            method: 'GET',
            url: rtrim((string) $dataSource->url, '/').'/v1/version',
            headers: ['Content-Type' => 'application/json'],
            username: DataSourceCredentials::username($dataSource),
            password: DataSourceCredentials::password($dataSource),
            evaluate: HealthProbe::httpStatusError(...),
        );
    }

    public function build(DataSource $dataSource): HealthProbe
    {
        return new HealthProbe(
            method: 'GET',
            url: rtrim((string) $dataSource->url, '/').'/api/health',
            username: DataSourceCredentials::username($dataSource),
            password: DataSourceCredentials::password($dataSource),
            evaluate: HealthProbe::httpStatusError(...),
        );
    }
}
