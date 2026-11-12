<?php

namespace App\Services;

use App\Models\VictoriaLogsCheck;
use App\Services\DataSource\DataSourceAvailability;
use Illuminate\Http\Client\ConnectionException;

class VictoriaLogsService
{
    public static function countDocuments(VictoriaLogsCheck $victoriaLogsCheck): ?int
    {
        $dataSource = $victoriaLogsCheck->alertRule->dataSource;
        $dataSourceId = (string) ($victoriaLogsCheck->alertRule->dataSourceId ?? '');
        $availability = app(DataSourceAvailability::class);

        if ($availability->isCoolingDown($dataSourceId)) {
            return null;
        }

        try {
            $timeString = $victoriaLogsCheck->minutes.'m';
            $response = \Http::acceptJson()
                ->connectTimeout((int) config('datasource_checks.connect_timeout', 2))
                ->timeout((int) config('datasource_checks.timeout', 5))
                ->get($dataSource->url.'/select/logsql/query', [
                    'query' => "_time:$timeString $victoriaLogsCheck->queryString | stats count() as total",
                ]);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->json();

            if (! is_array($body) || ! array_key_exists('total', $body)) {
                return null;
            }

            return (int) $body['total'];

        } catch (ConnectionException) {
            $availability->coolDown($dataSourceId);

            return null;
        } catch (\Exception) {
            return null;
        }
    }
}
