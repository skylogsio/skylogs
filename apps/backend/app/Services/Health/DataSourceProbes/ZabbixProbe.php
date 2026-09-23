<?php

namespace App\Services\Health\DataSourceProbes;

use App\Models\DataSource\DataSource;
use App\Services\Health\HealthProbe;
use Illuminate\Http\Client\Response;

final class ZabbixProbe implements DataSourceProbeBuilder
{
    public function build(DataSource $dataSource): HealthProbe
    {
        return new HealthProbe(
            method: 'POST',
            url: $dataSource->zabbixApiUrl(),
            json: [
                'jsonrpc' => '2.0',
                'method' => 'apiinfo.version',
                'params' => new \stdClass,
                'id' => 1,
            ],
            verifyTls: false,
            evaluate: function (Response $response): ?string {
                if (! $response->successful()) {
                    return 'HTTP '.$response->status();
                }

                $body = $response->json();

                if (! is_array($body) || array_key_exists('error', $body)) {
                    return 'Zabbix API error';
                }

                return null;
            },
        );
    }
}
