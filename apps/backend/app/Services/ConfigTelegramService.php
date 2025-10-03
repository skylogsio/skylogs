<?php

namespace App\Services;

use App\Enums\DataSourceType;
use App\Models\Config\ConfigTelegram;
use App\Models\DataSource\DataSource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;


class ConfigTelegramService
{


    public function getActive(): ?ConfigTelegram
    {
        return cache()->tags(['config',"telegram"])->rememberForever('config:telegram:active', function () {
            return ConfigTelegram::query()->where("active", true)->first();
        });
    }

    public function activate(ConfigTelegram $config): void
    {
        ConfigTelegram::query()->update(["active" => false]);
        $config->update(["active" => true]);
        $this->flushCache();
    }
    public function deactivate(): void
    {
        ConfigTelegram::query()->update(["active" => false]);
        $this->flushCache();
    }

    public function flushCache()
    {
        cache()->tags(['config', "telegram"])->flush();
    }

}
