<?php

namespace App\Observers;

use App\Models\Config\ConfigSkylogs;
use App\Services\ConfigSkylogsService;

class ConfigSkylogsObserver
{
    public function created(ConfigSkylogs $config): void
    {
        app(ConfigSkylogsService::class)->flushCache();
    }

    public function updated(ConfigSkylogs $config): void
    {
        app(ConfigSkylogsService::class)->flushCache();
    }

    public function deleted(ConfigSkylogs $config): void
    {
        app(ConfigSkylogsService::class)->flushCache();
    }

    public function restored(ConfigSkylogs $config): void
    {
        app(ConfigSkylogsService::class)->flushCache();
    }

    public function forceDeleted(ConfigSkylogs $config): void
    {
        app(ConfigSkylogsService::class)->flushCache();
    }
}
