<?php

namespace App\Services;

use App\Models\ElasticCheck;
use App\Models\VictoriaLogsCheck;
use Carbon\Carbon;

class VictoriaLogsService
{
    public static function countDocuments(VictoriaLogsCheck $victoriaLogsCheck): array
    {
        $documents = [];

        $dataSource = $victoriaLogsCheck->alertRule->dataSource;
        try {
            $nowCarbon = Carbon::now('UTC');
            $nowString = $nowCarbon->format("Y-m-d\TH:i:s");
            $agoString = $nowCarbon->subMinutes($victoriaLogsCheck->minutes)->format("Y-m-d\TH:i:s");
            //        dd($nowString,$agoString);
            $response = \Http::acceptJson()
                ->withBasicAuth($dataSource->username, $dataSource->password)
                ->post($dataSource->url."/$victoriaLogsCheck->dataviewTitle/_search",
                    [
                        'size' => $victoriaLogsCheck->countDocument + 10,
                        'query' => [
                            'query_string' => [
                                'query' => "timestamp:[$agoString TO $nowString] $victoriaLogsCheck->queryString",
                                'default_operator' => 'AND',
                            ],
                        ],
                    ]
                );
            $body = $response->json();

            $documents = $body['hits']['hits'];

        } catch (\Exception $exception) {

        }

        return $documents;

    }
}
