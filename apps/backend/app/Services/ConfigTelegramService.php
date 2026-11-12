<?php

namespace App\Services;

use App\Models\Config\ConfigTelegram;
use Illuminate\Support\Collection;

class ConfigTelegramService
{
    /**
     * Transports in send order. Direct is a stored profile; an empty chain sends directly.
     * A proxy activated before `priority` existed stays the first hop.
     *
     * @return Collection<int, ConfigTelegram>
     */
    public function chain(): Collection
    {
        return cache()->tags(['config', 'telegram'])->remember('config:telegram:chain', (int) config('cache.ttl'), function () {
            $chain = ConfigTelegram::query()->where('priority', '>=', 1)->orderBy('priority')->get();

            if ($chain->isNotEmpty()) {
                return $chain;
            }

            $active = ConfigTelegram::query()->where('active', true)->first();

            return $active === null ? collect() : collect([$active]);
        });
    }

    public function getActive(): ?ConfigTelegram
    {
        return cache()->tags(['config', 'telegram'])->remember('config:telegram:active', (int) config('cache.ttl'), function () {
            return ConfigTelegram::query()->where('active', true)->first();
        });
    }

    /**
     * @param  list<string>  $ids  First id is priority 1 and stays the active config. Every other config leaves the chain.
     */
    public function setPriorities(array $ids): void
    {
        $rank = [];

        foreach (array_values($ids) as $index => $id) {
            $rank[(string) $id] = $index + 1;
        }

        ConfigTelegram::query()->get()->each(function (ConfigTelegram $config) use ($rank): void {
            $priority = $rank[(string) $config->getKey()] ?? null;

            $config->update([
                'priority' => $priority,
                'active' => $priority === 1,
            ]);
        });

        $this->flushCache();
    }

    public function activate(ConfigTelegram $config): void
    {
        $this->setPriorities([(string) $config->getKey()]);
    }

    public function deactivate(): void
    {
        $this->setPriorities([]);
    }

    public function flushCache(): void
    {
        cache()->tags(['config', 'telegram'])->flush();
    }
}
