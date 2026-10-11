<?php

namespace App\Services;

use App\Models\ElasticCheck;
use App\Services\DataSource\DataSourceAvailability;
use Illuminate\Http\Client\ConnectionException;

class ElasticService
{
    public const TIME_FIELD = 'timestamp';

    public static function countDocuments(ElasticCheck $elasticCheck): ?int
    {
        $dataSource = $elasticCheck->alertRule->dataSource;
        $dataSourceId = (string) ($elasticCheck->alertRule->dataSourceId ?? '');
        $availability = app(DataSourceAvailability::class);

        if ($availability->isCoolingDown($dataSourceId)) {
            return null;
        }

        try {
            $minutes = (int) $elasticCheck->minutes;

            $response = \Http::acceptJson()
                ->withBasicAuth($dataSource->username, $dataSource->password)
                ->connectTimeout((int) config('datasource_checks.connect_timeout', 2))
                ->timeout((int) config('datasource_checks.timeout', 5))
                ->post($dataSource->url."/{$elasticCheck->dataviewTitle}/_count", [
                    'query' => [
                        'bool' => [
                            'filter' => [
                                ['range' => [self::TIME_FIELD => ['gte' => "now-{$minutes}m", 'lte' => 'now']]],
                                ['query_string' => [
                                    'query' => $elasticCheck->queryString,
                                    'default_operator' => 'AND',
                                ]],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->json();

            return (int) ($body['count'] ?? 0);

        } catch (ConnectionException) {
            $availability->coolDown($dataSourceId);

            return null;
        } catch (\Exception) {
            return null;
        }
    }
}
