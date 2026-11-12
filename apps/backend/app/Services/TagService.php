<?php

namespace App\Services;

use App\Models\AlertRule;

class TagService
{
    public function all(): array
    {
        return cache()->tags(['alertRule', 'tags'])->remember('alertRule:tags', (int) config('cache.ttl'), function () {

            $array = AlertRule::select('tags')->distinct()->get()->toArray();

            return collect($array)->flatten()->toArray();
        });

    }

    public function flushCache(): void
    {
        cache()->tags(['alertRule', 'tags'])->flush();
    }
}
