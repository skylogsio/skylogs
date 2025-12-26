<?php

namespace App\Services;

use App\Models\Config\ConfigSms;

class ConfigSmsService
{
    public function getDefault(): ?ConfigSms
    {
        return cache()->tags(['config', 'sms'])->rememberForever('config:sms:default', function () {
            return ConfigSms::query()->where('isDefault', true)->first();
        });
    }

    public function makeDefault(ConfigSms $config): void
    {
        ConfigSms::query()->update(['isDefault' => false]);
        $config->update(['isDefault' => true, 'isBackUp' => false]);
        $this->flushCache();
    }

    public function makeBackUp(ConfigSms $config): void
    {
        throw_if($config->isDefault, 'This Config SMS is default');

        ConfigSms::query()->update(['isBackUp' => false]);
        $config->update(['isBackUp' => true]);
        $this->flushCache();
    }

    public function flushCache()
    {
        cache()->tags(['config', 'sms'])->flush();
    }
}
