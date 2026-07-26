<?php

namespace App\Services\Ha;

use App\Exceptions\Ha\RaftUnavailableException;
use App\Jobs\Ha\PublishAlertStateJob;
use App\Models\AlertRule;
use InvalidArgumentException;

/**
 * Brings this node back into agreement with the replicated log.
 *
 * Replication is best effort by design: a publish is queued so that a slow
 * sidecar cannot stall alert evaluation, which means a publish can be lost.
 * Reconciliation is what makes that acceptable. It runs every minute on every
 * node, at boot, and immediately after a role change, when a freshly promoted
 * leader would otherwise evaluate against state it never received.
 */
class HaReconciler
{
    public function __construct(
        private readonly RaftClient $raft,
        private readonly HaLeaderService $leader,
        private readonly HaStateApplier $applier,
        private readonly HaStateVersionStore $versions,
        private readonly AlertStateReplicator $replicator,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws RaftUnavailableException
     */
    public function reconcile(): array
    {
        if (! config('ha.enabled')) {
            return ['role' => 'standalone'];
        }

        $data = $this->raft->state()['data'];

        return $this->leader->isLeader()
            ? $this->reconcileAsLeader($data)
            : $this->reconcileAsFollower($data);
    }

    /**
     * A follower takes the log as the truth: apply every key through the same
     * version gate a live delivery goes through, then drop the slots the log no
     * longer carries.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function reconcileAsFollower(array $data): array
    {
        $applied = 0;

        foreach ($data as $key => $value) {
            $result = $this->applier->apply((string) $key, is_array($value) ? $value : null);

            if ($result['applied']) {
                $applied++;
            }
        }

        $removed = 0;

        foreach ($this->versions->allKeys() as $key) {
            if (array_key_exists($key, $data)) {
                continue;
            }

            $this->applier->apply($key, null);
            $removed++;
        }

        return ['role' => 'follower', 'applied' => $applied, 'removed' => $removed];
    }

    /**
     * A leader treats its own documents as the truth, but only after adopting
     * the log's counters: a node promoted mid-life has never published these
     * slots, and publishing from version one would lose to its own history.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function reconcileAsLeader(array $data): array
    {
        $inherited = $this->inheritVersions($data);

        /*
         | Sweeping first so that a slot on its way out of the log is not
         | republished on the same pass only to be tombstoned again.
         */
        $swept = $this->sweepExpiredSlots();
        $republished = $this->republishStaleSlots($data);

        return [
            'role' => 'leader',
            'inherited' => $inherited,
            'republished' => $republished,
            'swept' => $swept,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function inheritVersions(array $data): int
    {
        $inherited = 0;

        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $version = (int) ($value['version'] ?? 0);

            if ($version <= $this->versions->current((string) $key)) {
                continue;
            }

            $this->versions->record(
                (string) $key,
                $version,
                (string) ($value['nodeId'] ?? ''),
                isset($value['state']) ? (string) $value['state'] : null,
            );

            $inherited++;
        }

        return $inherited;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function republishStaleSlots(array $data): int
    {
        $alertRuleIds = [];

        foreach ($this->versions->allKeys() as $key) {
            $remote = $data[$key] ?? null;
            $remoteVersion = is_array($remote) ? (int) ($remote['version'] ?? 0) : 0;

            if ($remoteVersion >= $this->versions->current($key)) {
                continue;
            }

            try {
                $alertRuleIds[AlertStateKey::parse($key)->alertRuleId] = true;
            } catch (InvalidArgumentException) {
                $this->versions->forget($key);
            }
        }

        $republished = 0;

        foreach (array_keys($alertRuleIds) as $alertRuleId) {
            $alertRule = AlertRule::find($alertRuleId);

            if (! $alertRule) {
                continue;
            }

            $this->replicator->republishRule($alertRule);
            $republished++;
        }

        return $republished;
    }

    /**
     * Resolved slots are kept for a while so a follower that was down still
     * sees the resolve, then tombstoned so the log stays bounded.
     */
    private function sweepExpiredSlots(): int
    {
        $retentionDays = (int) config('ha.state_retention_days');

        if ($retentionDays <= 0) {
            return 0;
        }

        $keys = $this->versions->resolvedKeysUpdatedBefore(now()->subDays($retentionDays));

        foreach ($keys as $key) {
            $this->versions->forget($key);
            PublishAlertStateJob::dispatch($key, null);
        }

        return count($keys);
    }
}
