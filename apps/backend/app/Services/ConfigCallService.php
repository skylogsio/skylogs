<?php

namespace App\Services;

use App\Models\Config\ConfigCall;

class ConfigCallService
{
    public function getDefault(): ?ConfigCall
    {
        return cache()->tags(['config', 'call'])->rememberForever('config:call:default', function () {
            return ConfigCall::query()->where('isDefault', true)->first();
        });
    }

    public function makeDefault(ConfigCall $config): void
    {
        ConfigCall::query()->update(['isDefault' => false]);
        $config->update(['isDefault' => true, 'isBackUp' => false]);
        $this->flushCache();
    }

    public function makeBackUp(ConfigCall $config): void
    {
        throw_if($config->isDefault, 'This Config Call is default');

        ConfigCall::query()->update(['isBackUp' => false]);
        $config->update(['isBackUp' => true]);
        $this->flushCache();
    }

    public function flushCache()
    {
        cache()->tags(['config', 'call'])->flush();
    }
}
