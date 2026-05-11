<?php

namespace App\Services;

use App\Models\VictoriaLogsCheck;

class VictoriaLogsService
{
    public static function countDocuments(VictoriaLogsCheck $victoriaLogsCheck): int
    {
        $documents = 0;

        $dataSource = $victoriaLogsCheck->alertRule->dataSource;
        try {
            $timeString = $victoriaLogsCheck->minutes.'m';
            $response = \Http::acceptJson()
                ->get($dataSource->url.'/select/logsql/query',
                    [
                        'query' => "_time:$timeString $victoriaLogsCheck->queryString | stats count() as total",
                    ]
                );
            $body = $response->json();

            $documents = (int) $body['total'];

        } catch (\Exception $exception) {

        }

        return $documents;

    }
}
