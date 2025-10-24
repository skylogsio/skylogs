<?php

namespace App\Observers;

use App\Models\DataSource\DataSource;
use App\Services\DataSourceService;

class DataSourceObserver
{
    public function created(DataSource $dataSource): void
    {
        app(DataSourceService::class)->flushCache();
    }

    public function updated(DataSource $dataSource): void
    {
        app(DataSourceService::class)->flushCache();
    }

    public function deleted(DataSource $dataSource): void
    {
        app(DataSourceService::class)->flushCache();
    }

    public function restored(DataSource $dataSource): void
    {
        app(DataSourceService::class)->flushCache();
    }

    public function forceDeleted(DataSource $dataSource): void
    {
        app(DataSourceService::class)->flushCache();
    }
}
