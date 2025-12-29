<?php

namespace App\Services;

use App\Enums\DataSourceType;
use App\Models\DataSource\DataSource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

class DataSourceService
{
    public function isConnected(string $dataSourceId): bool
    {
        $ds = DataSource::query()->whereId($dataSourceId)->firstOrFail();

        try {
            $request = Http::timeout(5);
            switch ($ds->type) {
                case DataSourceType::GRAFANA:
                case DataSourceType::PROMETHEUS:
                case DataSourceType::SENTRY:
                case DataSourceType::PMM:
                case DataSourceType::SPLUNK:
                case DataSourceType::ZABBIX:
                    $response = $request->get($ds->url);
                    break;
                case DataSourceType::ELASTIC:
                    $response = $request->withBasicAuth($ds->username,$ds->password)->get($ds->url."/_search?size=0");
                    break;
            }

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }

    }

    public function get(?DataSourceType $dataSourceType = null): Collection
    {
        $tagsArray = ['dataSource'];
        $keyName = 'dataSource';
        if ($dataSourceType) {
            $tagsArray[] = $dataSourceType->value;
            $keyName .= ':'.$dataSourceType->value;
        }

        $dataSources = cache()
            ->tags($tagsArray)
            ->rememberForever($keyName, function () use ($dataSourceType) {
                $dataSource = DataSource::query();
                if ($dataSourceType) {
                    $dataSource = $dataSource->where('type', $dataSourceType);
                }

                return $dataSource->get()->keyBy('id');
            });

        return $dataSources;
    }

    public function byToken($token): ?DataSource
    {
        return DataSource::query()->where('webhookToken', $token)->firstOrFail();
    }

    public static function flushCache()
    {
        cache()->tags(['dataSource'])->flush();
    }
}
