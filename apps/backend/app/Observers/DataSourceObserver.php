<?php

namespace App\Observers;

use App\Models\DataSource\DataSource;
use App\Services\AlertRuleService;
use App\Services\DataSourceService;

class DataSourceObserver
{
    public function created(DataSource $dataSource): void
    {
        app(DataSourceService::class)->flushCache();
        app(AlertRuleService::class)->createHealthDataSource($dataSource);
    }

    public function updated(DataSource $dataSource): void
    {
        app(DataSourceService::class)->flushCache();
    }

    public function deleted(DataSource $dataSource): void
    {
        app(DataSourceService::class)->flushCache();
        app(AlertRuleService::class)->deleteHealthDataSource($dataSource);
    }

    public function restored(DataSource $dataSource): void
    {
        app(DataSourceService::class)->flushCache();
    }

    public function forceDeleted(DataSource $dataSource): void
    {
        app(DataSourceService::class)->flushCache();
        app(AlertRuleService::class)->deleteHealthDataSource($dataSource);
    }
}
