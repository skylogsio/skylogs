<?php

namespace App\Services\Ha;

use App\Exceptions\Ha\RaftUnavailableException;
use App\Jobs\Ha\ReconcileHaStateJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Answers leadership questions from the local Raft sidecar's GET /leader.
 */
class HaLeaderService
{
    private const LEADER_CACHE_KEY = 'ha:leaderStatus';

    private const LAST_ROLE_CACHE_KEY = 'ha:lastRole';

    private const ROLE_LEADER = 'leader';

    private const ROLE_FOLLOWER = 'follower';

    public function __construct(private readonly RaftClient $raft) {}

    public function isLeader(): bool
    {
        return $this->leaderInfo()['isLeader'];
    }

    /**
     * Base URL of the leader's backend (never the load balancer).
     * Maps /leader's leaderNode through HA_PEER_URLS.
     */
    public function leaderAddress(): ?string
    {
        return $this->peerUrl($this->leaderInfo()['leaderNode']);
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
     * @return array{isLeader: bool, leaderNode: string|null}
     */
    private function leaderInfo(): array
    {
        if (! config('ha.enabled')) {
            return $this->unknownLeader();
        }

        $cacheSeconds = (int) config('ha.leader_cache_seconds');

        if ($cacheSeconds > 0) {
            $cached = Cache::get(self::LEADER_CACHE_KEY);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $info = $this->resolveLeader();

        if ($cacheSeconds > 0) {
            Cache::put(self::LEADER_CACHE_KEY, $info, $cacheSeconds);
        }

        $this->handleRoleTransition($info['isLeader']);

        return $info;
    }

    /**
     * @return array{isLeader: bool, leaderNode: string|null}
     */
    private function resolveLeader(): array
    {
        try {
            $leader = $this->raft->leader();

            return [
                'isLeader' => $leader['isLeader'],
                'leaderNode' => $leader['leaderNode'],
            ];
        } catch (RaftUnavailableException $exception) {
            Log::warning('HA leader check failed, treating this node as a follower.', [
                'nodeId' => $this->nodeId(),
                ...$exception->context(),
            ]);

            return $this->unknownLeader();
        }
    }

    /**
     * @return array{isLeader: bool, leaderNode: string|null}
     */
    private function unknownLeader(): array
    {
        return [
            'isLeader' => false,
            'leaderNode' => null,
        ];
    }

    private function peerUrl(?string $nodeId): ?string
    {
        if ($nodeId === null || $nodeId === '') {
            return null;
        }

        /** @var array<string, string> $peers */
        $peers = (array) config('ha.peers');
        $url = $peers[$nodeId] ?? null;

        return is_string($url) && $url !== '' ? rtrim($url, '/') : null;
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
            ReconcileHaStateJob::dispatch();
        }
    }
}
