<?php

namespace App\Observers;

use App\Models\Config\ConfigCall;
use App\Services\ConfigCallService;

class ConfigCallObserver
{
    public function created(ConfigCall $configCall): void
    {
        app(ConfigCallService::class)->flushCache();
    }

    public function updated(ConfigCall $configCall): void
    {
        app(ConfigCallService::class)->flushCache();
    }

    public function deleted(ConfigCall $configCall): void
    {
        app(ConfigCallService::class)->flushCache();
    }

    public function restored(ConfigCall $configCall): void
    {
        app(ConfigCallService::class)->flushCache();
    }

    public function forceDeleted(ConfigCall $configCall): void
    {
        app(ConfigCallService::class)->flushCache();
    }
}
