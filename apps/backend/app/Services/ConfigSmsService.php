<?php

namespace App\Services;

use App\Models\Config\ConfigSms;
use Illuminate\Support\Collection;

class ConfigSmsService
{
    /**
     * Profiles in send order. A chain stored before `priority` existed is the default row.
     *
     * @return Collection<int, ConfigSms>
     */
    public function chain(): Collection
    {
        return cache()->tags(['config', 'sms'])->remember('config:sms:chain', (int) config('cache.ttl'), function () {
            $chain = ConfigSms::query()->where('priority', '>=', 1)->orderBy('priority')->get();

            if ($chain->isNotEmpty()) {
                return $chain;
            }

            $default = ConfigSms::query()->where('isDefault', true)->first();

            return $default === null ? collect() : collect([$default]);
        });
    }

    public function getDefault(): ?ConfigSms
    {
        return cache()->tags(['config', 'sms'])->remember('config:sms:default', (int) config('cache.ttl'), function () {
            return ConfigSms::query()->where('isDefault', true)->first();
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

        ConfigSms::query()->get()->each(function (ConfigSms $config) use ($rank): void {
            $priority = $rank[(string) $config->getKey()] ?? null;

            $config->update([
                'priority' => $priority,
                'isDefault' => $priority === 1,
                'isBackUp' => $priority === 2,
            ]);
        });

        $this->flushCache();
    }

    public function makeDefault(ConfigSms $config): void
    {
        $backupId = ConfigSms::query()
            ->where('isBackUp', true)
            ->where('_id', '!=', $config->getKey())
            ->value('_id');

        $ids = [(string) $config->getKey()];

        if ($backupId !== null) {
            $ids[] = (string) $backupId;
        }

        $this->setPriorities($ids);
    }

    public function makeBackUp(ConfigSms $config): void
    {
        throw_if($config->isDefault, 'This Config SMS is default');

        $defaultId = ConfigSms::query()->where('isDefault', true)->value('_id');
        $ids = [];

        if ($defaultId !== null) {
            $ids[] = (string) $defaultId;
        }

        $ids[] = (string) $config->getKey();

        $this->setPriorities($ids);
    }

    public function flushCache(): void
    {
        cache()->tags(['config', 'sms'])->flush();
    }
}
