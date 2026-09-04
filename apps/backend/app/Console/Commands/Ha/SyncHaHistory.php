<?php

namespace App\Console\Commands\Ha;

use App\Exceptions\Ha\LeaderUnavailableException;
use App\Services\Ha\HaHistoryPuller;
use Illuminate\Console\Command;

class SyncHaHistory extends Command
{
    protected $signature = 'ha:history-sync';

    protected $description = 'Pull leader alert history and notify documents onto this node';

    public function handle(HaHistoryPuller $puller): int
    {
        try {
            $result = $puller->pull();
        } catch (LeaderUnavailableException $exception) {
            $this->components->warn('The leader is unreachable: '.$exception->getMessage());

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('status', $result['status']);

        foreach ($result['collections'] ?? [] as $collection => $counts) {
            $this->components->twoColumnDetail(
                $collection,
                "written {$counts['written']}, pages {$counts['pages']}, hasMore ".($counts['hasMore'] ? 'yes' : 'no'),
            );
        }

        return self::SUCCESS;
    }
}
