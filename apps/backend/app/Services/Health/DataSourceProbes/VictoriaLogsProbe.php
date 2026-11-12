<?php

namespace App\Services\Health\DataSourceProbes;

use App\Models\DataSource\DataSource;
use App\Services\Health\HealthProbe;

final class VictoriaLogsProbe implements DataSourceProbeBuilder
{
    public function build(DataSource $dataSource): HealthProbe
    {
        return new HealthProbe(
            method: 'GET',
            url: rtrim((string) $dataSource->url, '/').'/health',
            evaluate: HealthProbe::httpStatusError(...),
        );
    }
}
