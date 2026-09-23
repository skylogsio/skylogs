<?php

namespace App\Services\Health\DataSourceProbes;

use App\Models\DataSource\DataSource;
use App\Services\Health\HealthProbe;
use Illuminate\Http\Client\Response;

final class ElasticProbe implements DataSourceProbeBuilder
{
    public function build(DataSource $dataSource): HealthProbe
    {
        return new HealthProbe(
            method: 'GET',
            url: rtrim((string) $dataSource->url, '/').'/_cluster/health',
            username: DataSourceCredentials::username($dataSource),
            password: DataSourceCredentials::password($dataSource),
            evaluate: function (Response $response): ?string {
                if (! $response->successful()) {
                    return 'HTTP '.$response->status();
                }

                if ($response->json('status') === 'red') {
                    return 'cluster status red';
                }

                return null;
            },
        );
    }
}
