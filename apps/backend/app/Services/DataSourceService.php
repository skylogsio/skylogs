<?php

namespace App\Services;

use App\Enums\DataSourceType;
use App\Models\DataSource\DataSource;
use App\Services\Health\DataSourceProbes\DataSourceProbeFactory;
use App\Services\Health\HealthProbeSender;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;

class DataSourceService
{
    public function __construct(
        private DataSourceProbeFactory $probes,
        private HealthProbeSender $sender,
    ) {}

    public function isConnected(string $dataSourceId): bool
    {
        $dataSource = DataSource::query()->findOrFail($dataSourceId);

        if (! is_string($dataSource->url) || $dataSource->url === '') {
            return false;
        }

        try {
            $probe = $this->probes->for($dataSource);

            return $probe->errorFor($this->sender->send($probe)) === null;
        } catch (ConnectionException) {
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

    public function byWebhookToken(string $token, DataSourceType $type): ?DataSource
    {
        if ($token === '') {
            return null;
        }

        return DataSource::query()
            ->where('webhookToken', $token)
            ->where('type', $type)
            ->first();
    }

    public static function flushCache()
    {
        cache()->tags(['dataSource'])->flush();
    }
}
