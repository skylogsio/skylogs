<?php

namespace App\Console\Commands\Ha;

use App\Exceptions\Ha\LeaderUnavailableException;
use App\Services\Ha\HaConfigPuller;
use Illuminate\Console\Command;

class SyncHaConfig extends Command
{
    protected $signature = 'ha:config-sync';

    protected $description = 'Pull the leader configuration snapshot onto this node';

    public function handle(HaConfigPuller $puller): int
    {
        try {
            $result = $puller->pull();
        } catch (LeaderUnavailableException $exception) {
            $this->components->warn('The leader is unreachable: '.$exception->getMessage());

            /*
             | Deliberately not a failure. This command runs from the container
             | entrypoint, where an election still in progress must not stop the
             | application from booting.
             */
            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('status', $result['status']);

        if (isset($result['version'])) {
            $this->components->twoColumnDetail('version', (string) $result['version']);
        }

        foreach ($result['applied'] ?? [] as $collection => $counts) {
            $this->components->twoColumnDetail(
                $collection,
                "written {$counts['written']}, deleted {$counts['deleted']}",
            );
        }

        return self::SUCCESS;
    }
}
