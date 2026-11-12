<?php

namespace App\Services\DataSource;

use Illuminate\Support\Facades\Cache;

class DataSourceAvailability
{
    public function isCoolingDown(string $dataSourceId): bool
    {
        if ($dataSourceId === '') {
            return false;
        }

        return Cache::has($this->key($dataSourceId));
    }

    public function coolDown(string $dataSourceId): void
    {
        if ($dataSourceId === '') {
            return;
        }

        Cache::put(
            $this->key($dataSourceId),
            true,
            (int) config('datasource_checks.cooldown_seconds', 60),
        );
    }

    private function key(string $dataSourceId): string
    {
        return 'datasource-unreachable:'.$dataSourceId;
    }
}
