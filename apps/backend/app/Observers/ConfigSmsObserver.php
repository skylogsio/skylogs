<?php

namespace App\Observers;

use App\Models\Config\ConfigSms;
use App\Services\ConfigSmsService;

class ConfigSmsObserver
{
    public function created(ConfigSms $configSms): void
    {
        app(ConfigSmsService::class)->flushCache();
    }

    public function updated(ConfigSms $configSms): void
    {
        app(ConfigSmsService::class)->flushCache();
    }

    public function deleted(ConfigSms $configSms): void
    {
        app(ConfigSmsService::class)->flushCache();
    }

    public function restored(ConfigSms $configSms): void
    {
        app(ConfigSmsService::class)->flushCache();
    }

    public function forceDeleted(ConfigSms $configSms): void
    {
        app(ConfigSmsService::class)->flushCache();
    }
}
