<?php

namespace App\Services\Health\DataSourceProbes;

use App\Models\DataSource\DataSource;
use App\Services\Health\HealthProbe;

final class SplunkProbe implements DataSourceProbeBuilder
{
    public function build(DataSource $dataSource): HealthProbe
    {
        $token = DataSourceCredentials::token($dataSource);

        return new HealthProbe(
            method: 'GET',
            url: rtrim((string) $dataSource->url, '/').'/services/server/info?output_mode=json',
            username: $token === null ? DataSourceCredentials::username($dataSource) : null,
            password: $token === null ? DataSourceCredentials::password($dataSource) : null,
            bearerToken: $token,
            evaluate: HealthProbe::httpStatusError(...),
        );
    }
}
