<?php

namespace App\Services\Health\DataSourceProbes;

use App\Models\DataSource\DataSource;
use App\Services\Health\HealthProbe;

final class SentryProbe implements DataSourceProbeBuilder
{
    public function build(DataSource $dataSource): HealthProbe
    {
        return new HealthProbe(
            method: 'GET',
            url: rtrim((string) $dataSource->url, '/').'/api/0/',
            bearerToken: DataSourceCredentials::token($dataSource),
            evaluate: HealthProbe::httpStatusError(...),
        );
    }
}
