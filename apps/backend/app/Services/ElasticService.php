<?php

namespace App\Services;

use App\Models\ElasticCheck;

class ElasticService
{
    public const TIME_FIELD = 'timestamp';

    public static function countDocuments(ElasticCheck $elasticCheck): ?int
    {
        $dataSource = $elasticCheck->alertRule->dataSource;

        try {
            $minutes = (int) $elasticCheck->minutes;

            $response = \Http::acceptJson()
                ->withBasicAuth($dataSource->username, $dataSource->password)
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

        } catch (\Exception $exception) {
            return null;
        }
    }
}
