<?php

namespace App\Services\Ha;

use App\Exceptions\Ha\LeaderUnavailableException;

/**
 * The follower half of configuration sync: ask the leader, apply what comes
 * back, remember the version so the next ask is cheap.
 */
class HaConfigPuller
{
    public function __construct(
        private readonly HaLeaderService $leader,
        private readonly LeaderConfigClient $client,
        private readonly HaConfigSyncService $sync,
        private readonly HaConfigVersionStore $versions,
    ) {}

    /**
     * @return array{status: string, version?: int, applied?: array<string, array{written: int, deleted: int}>}
     *
     * @throws LeaderUnavailableException
     */
    public function pull(): array
    {
        if (! config('ha.enabled') || ! config('ha.config_sync.enabled')) {
            return ['status' => 'disabled'];
        }

        /*
         | The leader is the source, so it has nothing to pull. Skipping here
         | rather than in the caller means the boot command and the scheduled
         | job both behave correctly whichever role this node holds.
         */
        if ($this->leader->isLeader()) {
            return ['status' => 'leader'];
        }

        $address = $this->leader->leaderAddress();

        if (! is_string($address) || $address === '') {
            throw LeaderUnavailableException::unknownAddress();
        }

        $since = $this->versions->lastAppliedLeaderVersion();
        $snapshot = $this->client->snapshot($address, $since);

        if (! $snapshot['changed']) {
            return ['status' => 'upToDate', 'version' => $snapshot['version']];
        }

        $applied = $this->sync->apply($snapshot);

        /*
         | Recorded only after a successful apply. A partial apply that threw
         | leaves the version behind, so the next tick pulls the same snapshot
         | again and finishes the job.
         */
        $this->versions->recordAppliedLeaderVersion($snapshot['version']);

        return ['status' => 'applied', 'version' => $snapshot['version'], 'applied' => $applied];
    }
}
