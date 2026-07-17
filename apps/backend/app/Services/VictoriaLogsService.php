<?php

namespace App\Services;

use App\Models\VictoriaLogsCheck;

class VictoriaLogsService
{
    public static function countDocuments(VictoriaLogsCheck $victoriaLogsCheck): ?int
    {
        $dataSource = $victoriaLogsCheck->alertRule->dataSource;

        try {
            $timeString = $victoriaLogsCheck->minutes.'m';
            $response = \Http::acceptJson()
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

        } catch (\Exception $exception) {
            return null;
        }
    }
}
