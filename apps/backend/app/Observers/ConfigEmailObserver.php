<?php

namespace App\Observers;

use App\Models\Config\ConfigEmail;
use App\Services\ConfigEmailService;

class ConfigEmailObserver
{
    public function created(ConfigEmail $configEmail): void
    {
        app(ConfigEmailService::class)->flushCache();
    }

    public function updated(ConfigEmail $configEmail): void
    {
        app(ConfigEmailService::class)->flushCache();
    }

    public function deleted(ConfigEmail $configEmail): void
    {
        app(ConfigEmailService::class)->flushCache();
    }

    public function restored(ConfigEmail $configEmail): void
    {
        app(ConfigEmailService::class)->flushCache();
    }

    public function forceDeleted(ConfigEmail $configEmail): void
    {
        app(ConfigEmailService::class)->flushCache();
    }
}
