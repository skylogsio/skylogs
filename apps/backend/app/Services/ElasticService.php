<?php

namespace App\Services;

use App\Models\ElasticCheck;
use Carbon\Carbon;

class ElasticService
{
    public static function getDocuments(ElasticCheck $elasticCheck): array
    {
        $documents = [];

        $dataSource = $elasticCheck->alertRule->dataSource;
        try {
            $nowCarbon  = Carbon::now("UTC");
            $nowString = $nowCarbon->format("Y-m-d\TH:i:s");
            $agoString = $nowCarbon->subMinutes($elasticCheck->minutes)->format("Y-m-d\TH:i:s");
//        dd($nowString,$agoString);
            $response = \Http::acceptJson()
                ->withBasicAuth($dataSource->username, $dataSource->password)
                ->post($dataSource->url."/$elasticCheck->dataviewTitle/_search",
                [
                    "size" => $elasticCheck->countDocument + 10,
                    "query" => [
                        "query_string" => [
                            "query" => "timestamp:[$agoString TO $nowString] $elasticCheck->queryString",
                            "default_operator" => "AND"
                        ]
                    ]
                ]
            );
            $body = $response->json();

            $documents = $body['hits']["hits"];

        } catch (\Exception $exception) {

        }

        return $documents;

    }

}
