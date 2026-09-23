<?php

namespace App\Services\Health\DataSourceProbes;

use App\Models\DataSource\DataSource;
use App\Services\Health\HealthProbe;

final class GrafanaProbe implements DataSourceProbeBuilder
{
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
