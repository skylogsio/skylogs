<?php

namespace App\Services;

use App\Models\Config\ConfigEmail;

class ConfigEmailService
{
    public function getDefault(): ?ConfigEmail
    {
        return cache()->tags(['config', 'email'])->rememberForever('config:email:default', function () {
            return ConfigEmail::query()->where('isDefault', true)->first();
        });
    }

    public function makeDefault(ConfigEmail $config): void
    {
        ConfigEmail::query()->update(['isDefault' => false]);
        $config->update(['isDefault' => true, 'isBackUp' => false]);
        $this->flushCache();
    }

    public function makeBackUp(ConfigEmail $config): void
    {
        throw_if($config->isDefault, 'This Config Email is default');

        ConfigEmail::query()->update(['isBackUp' => false]);
        $config->update(['isBackUp' => true]);
        $this->flushCache();
    }

    public function flushCache()
    {
        cache()->tags(['config', 'email'])->flush();
    }
}
