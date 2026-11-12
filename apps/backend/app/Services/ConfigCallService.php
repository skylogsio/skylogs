<?php

namespace App\Services;

use App\Models\Config\ConfigCall;
use Illuminate\Support\Collection;

class ConfigCallService
{
    /**
     * Profiles in send order. A chain stored before `priority` existed is the default row.
     *
     * @return Collection<int, ConfigCall>
     */
    public function chain(): Collection
    {
        return cache()->tags(['config', 'call'])->remember('config:call:chain', (int) config('cache.ttl'), function () {
            $chain = ConfigCall::query()->where('priority', '>=', 1)->orderBy('priority')->get();

            if ($chain->isNotEmpty()) {
                return $chain;
            }

            $default = ConfigCall::query()->where('isDefault', true)->first();

            return $default === null ? collect() : collect([$default]);
        });
    }

    public function getDefault(): ?ConfigCall
    {
        return cache()->tags(['config', 'call'])->remember('config:call:default', (int) config('cache.ttl'), function () {
            return ConfigCall::query()->where('isDefault', true)->first();
        });
    }

    /**
     * @param  list<string>  $ids  First id is priority 1. Every other config leaves the chain.
     */
    public function setPriorities(array $ids): void
    {
        $rank = [];

        foreach (array_values($ids) as $index => $id) {
            $rank[(string) $id] = $index + 1;
        }

        ConfigCall::query()->get()->each(function (ConfigCall $config) use ($rank): void {
            $priority = $rank[(string) $config->getKey()] ?? null;

            $config->update([
                'priority' => $priority,
                'isDefault' => $priority === 1,
                'isBackUp' => $priority === 2,
            ]);
        });

        $this->flushCache();
    }

    public function makeDefault(ConfigCall $config): void
    {
        $backupId = ConfigCall::query()
            ->where('isBackUp', true)
            ->where('_id', '!=', $config->getKey())
            ->value('_id');

        $ids = [(string) $config->getKey()];

        if ($backupId !== null) {
            $ids[] = (string) $backupId;
        }

        $this->setPriorities($ids);
    }

    public function makeBackUp(ConfigCall $config): void
    {
        throw_if($config->isDefault, 'This Config Call is default');

        $defaultId = ConfigCall::query()->where('isDefault', true)->value('_id');
        $ids = [];

        if ($defaultId !== null) {
            $ids[] = (string) $defaultId;
        }

        $ids[] = (string) $config->getKey();

        $this->setPriorities($ids);
    }

    public function flushCache(): void
    {
        cache()->tags(['config', 'call'])->flush();
    }
}
