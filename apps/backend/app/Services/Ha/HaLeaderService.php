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
     * is the node's own advertised address, never the load balancer's.
     */
    public function leaderAddress(): ?string
    {
        return $this->status()['leaderAddress'];
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
     * @return array{isLeader: bool, nodeId: string, leaderId: string|null, leaderAddress: string|null, term: int|null}
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
     * @return array{isLeader: bool, nodeId: string, leaderId: string|null, leaderAddress: string|null, term: int|null}
     */
    private function resolveStatus(): array
    {
        try {
            return $this->raft->leader();
        } catch (RaftUnavailableException $exception) {
            Log::warning('HA leader check failed, treating this node as a follower.', [
                'nodeId' => $this->nodeId(),
                ...$exception->context(),
            ]);

            return $this->followerStatus();
        }
    }

    /**
     * @return array{isLeader: bool, nodeId: string, leaderId: string|null, leaderAddress: string|null, term: int|null}
     */
    private function followerStatus(): array
    {
        return [
            'isLeader' => false,
            'nodeId' => $this->nodeId(),
            'leaderId' => null,
            'leaderAddress' => null,
            'term' => null,
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
