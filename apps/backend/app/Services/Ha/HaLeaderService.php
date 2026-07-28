<?php

namespace App\Services\Ha;

use App\Exceptions\Ha\RaftUnavailableException;
use App\Jobs\Ha\ReconcileHaStateJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Answers "is this node the leader?" from the local Raft sidecar.
 */
class HaLeaderService
{
    private const STATUS_CACHE_KEY = 'ha:leaderStatus';

    private const LAST_ROLE_CACHE_KEY = 'ha:lastRole';

    private const ROLE_LEADER = 'leader';

    private const ROLE_FOLLOWER = 'follower';

    public function __construct(private readonly RaftClient $raft) {}

    public function isLeader(): bool
    {
        return $this->status()['isLeader'];
    }

    /**
     * Base URL of the leader's backend, used by followers to pull config. This
     * is the node's own address, never the load balancer's.
     *
     * Followers now receive that backend URL directly from the sidecar. While
     * leading, this node still resolves its own URL from the configured peers.
     */
    public function leaderAddress(): ?string
    {
        $status = $this->status();

        return $status['isLeader']
            ? $this->peerUrl($status['nodeId'] !== '' ? $status['nodeId'] : $this->nodeId())
            : $status['leaderRaftAddress'];
    }

    /**
     * Identifier reported for the current leader. Older sidecars returned a
     * Raft address; newer ones return the leader backend URL directly.
     */
    public function leaderRaftAddress(): ?string
    {
        return $this->status()['leaderRaftAddress'];
    }

    public function nodeId(): string
    {
        return (string) config('ha.node_id');
    }

    /**
     * Whether leader-only work may run here. With HA switched off there is no
     * leadership to prove and the guard is bypassed, so a single node install
     * keeps running every scheduled job exactly as before.
     */
    public function shouldRunLeaderWork(): bool
    {
        return ! config('ha.enabled') || $this->isLeader();
    }

    /**
     * Resolves this node's configured backend URL from a node id or legacy
     * Raft address. A Raft address is matched with and without its port so one
     * entry covers both spellings.
     */
    private function peerUrl(?string $identifier): ?string
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        /** @var array<string, string> $peers */
        $peers = (array) config('ha.peers');

        foreach ([$identifier, strstr($identifier, ':', true)] as $candidate) {
            $url = is_string($candidate) ? ($peers[$candidate] ?? null) : null;

            if (is_string($url) && $url !== '') {
                return rtrim($url, '/');
            }
        }

        return null;
    }

    /**
     * @return array{isLeader: bool, nodeId: string, leaderRaftAddress: string|null, state: string|null}
     */
    private function status(): array
    {
        if (! config('ha.enabled')) {
            return $this->followerStatus();
        }

        $cacheSeconds = (int) config('ha.leader_cache_seconds');

        if ($cacheSeconds > 0) {
            $cached = Cache::get(self::STATUS_CACHE_KEY);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $status = $this->resolveStatus();

        if ($cacheSeconds > 0) {
            Cache::put(self::STATUS_CACHE_KEY, $status, $cacheSeconds);
        }

        $this->handleRoleTransition($status['isLeader']);

        return $status;
    }

    /**
     * A node that cannot prove leadership is a follower. A brief gap in
     * evaluation is cheap; two nodes both believing they lead means duplicate
     * calls and messages to on-call staff.
     *
     * @return array{isLeader: bool, nodeId: string, leaderRaftAddress: string|null, state: string|null}
     */
    private function resolveStatus(): array
    {
        try {
            return $this->raft->status();
        } catch (RaftUnavailableException $exception) {
            Log::warning('HA leader check failed, treating this node as a follower.', [
                'nodeId' => $this->nodeId(),
                ...$exception->context(),
            ]);

            return $this->followerStatus();
        }
    }

    /**
     * @return array{isLeader: bool, nodeId: string, leaderRaftAddress: string|null, state: string|null}
     */
    private function followerStatus(): array
    {
        return [
            'isLeader' => false,
            'nodeId' => $this->nodeId(),
            'leaderRaftAddress' => null,
            'state' => null,
        ];
    }

    private function handleRoleTransition(bool $isLeader): void
    {
        $role = $isLeader ? self::ROLE_LEADER : self::ROLE_FOLLOWER;
        $previousRole = Cache::get(self::LAST_ROLE_CACHE_KEY);

        if ($previousRole === $role) {
            return;
        }

        Cache::forever(self::LAST_ROLE_CACHE_KEY, $role);

        if ($previousRole === null) {
            return;
        }

        Log::info('HA role transition.', [
            'nodeId' => $this->nodeId(),
            'from' => $previousRole,
            'to' => $role,
        ]);

        if ($role === self::ROLE_LEADER) {
            $this->reconcileAfterPromotion();
        }
    }

    /**
     * A freshly promoted leader must catch up with the replicated log before it
     * evaluates anything, otherwise it would notify off stale local state.
     */
    private function reconcileAfterPromotion(): void
    {
        ReconcileHaStateJob::dispatch();
    }
}
