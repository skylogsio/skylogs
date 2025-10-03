<?php

namespace App\Observers;

use App\Models\Config\ConfigTelegram;
use App\Services\ConfigTelegramService;

class ConfigTelegramObserver {

    public function created(ConfigTelegram $configTelegram): void {
        app(ConfigTelegramService::class)->flushCache();
    }

    public function updated(ConfigTelegram $configTelegram): void {
        app(ConfigTelegramService::class)->flushCache();
    }

    public function deleted(ConfigTelegram $configTelegram): void {
        app(ConfigTelegramService::class)->flushCache();
    }

    public function restored(ConfigTelegram $configTelegram): void {
        app(ConfigTelegramService::class)->flushCache();
    }

    public function forceDeleted(ConfigTelegram $configTelegram): void {
        app(ConfigTelegramService::class)->flushCache();
    }

}
