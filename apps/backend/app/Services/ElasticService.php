<?php

namespace App\Services;

use App\Models\ElasticCheck;
use Carbon\Carbon;

class ElasticService
{
    public static function countDocuments(ElasticCheck $elasticCheck): int
    {
        $dataSource = $elasticCheck->alertRule->dataSource;

        try {
            $nowCarbon = Carbon::now('UTC');
            $nowString = $nowCarbon->format("Y-m-d\TH:i:s");
            $agoString = $nowCarbon->copy()->subMinutes($elasticCheck->minutes)->format("Y-m-d\TH:i:s");

            $response = \Http::acceptJson()
                ->withBasicAuth($dataSource->username, $dataSource->password)
                ->post($dataSource->url."/{$elasticCheck->dataviewTitle}/_count", [
                    'query' => [
                        'query_string' => [
                            'query' => "timestamp:[$agoString TO $nowString] {$elasticCheck->queryString}",
                            'default_operator' => 'AND',
                        ],
                    ],
                ]);

            $body = $response->json();

            return (int) ($body['count'] ?? 0);

        } catch (\Exception $exception) {
            return 0;
        }
    }
}
