<?php

namespace App\Services;

use App\Enums\ClusterType;
use App\Models\Config\ConfigSkylogs;

class ConfigSkylogsService
{
    public function getClusterType(): ClusterType
    {
        $config = ConfigSkylogs::where('name', 'cluster')->first();
        if ($config) {
            return ClusterType::from($config->type);
        }

        return ClusterType::MAIN;
    }

    public function cluster(): ?ConfigSkylogs
    {
        $config = ConfigSkylogs::where('name', 'cluster')->first();
        if ($config) {
            return $config;
        }

        return null;
    }

    public function flushCache()
    {
        cache()->tags(['config', 'skylogs'])->flush();
    }
}
