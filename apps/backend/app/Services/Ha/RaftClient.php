<?php

namespace App\Services\Ha;

use App\Exceptions\Ha\RaftUnavailableException;
use Closure;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin client for the Raft sidecar running next to this node.
 *
 * Endpoints: /status, /leader, /set, /get. Failures become
 * RaftUnavailableException so callers never handle HTTP errors.
 *
 * Stored values are raw JSON text: writes send objects, reads decode.
 */
class RaftClient
{
    /**
     * Local node view from GET /status.
     *
     * @return array{isLeader: bool, nodeId: string, leaderRaftAddress: string|null, state: string|null}
     *
     * @throws RaftUnavailableException
     */
    public function status(): array
    {
        $payload = $this->send(
            '/status',
            (float) config('ha.raft.timeout.status'),
            fn (PendingRequest $request): Response => $request->get('/status'),
        );

        // {"is_leader": bool, "leader": "<raft-address>", "node_id": string, "state": string}
        $leaderRaftAddress = (string) ($payload['leader'] ?? '');

        return [
            'isLeader' => (bool) ($payload['is_leader'] ?? false),
            'nodeId' => (string) ($payload['node_id'] ?? ''),
            'leaderRaftAddress' => $leaderRaftAddress === '' ? null : $leaderRaftAddress,
            'state' => isset($payload['state']) ? (string) $payload['state'] : null,
        ];
    }

    /**
     * Cluster leader from GET /leader. `address` is the Raft advertise address;
     * use `leaderNode` with HA_PEER_URLS for the leader's backend URL.
     *
     * @return array{isLeader: bool, leaderNode: string|null, leaderRaftAddress: string|null}
     *
     * @throws RaftUnavailableException
     */
    public function leader(): array
    {
        $payload = $this->send(
            '/leader',
            (float) config('ha.raft.timeout.leader'),
            fn (PendingRequest $request): Response => $request->get('/leader'),
            // Raft returns 503 on followers so VIP health checks only pass the
            // current leader. The JSON body still names leaderNode for peers.
            acceptStatuses: [200, 503],
        );

        // {"leader": bool, "leaderNode": "<raft-node-id>", "address": "<raft-address>"}
        $leaderNode = (string) ($payload['leaderNode'] ?? '');
        $leaderRaftAddress = (string) ($payload['address'] ?? '');

        return [
            'isLeader' => (bool) ($payload['leader'] ?? false),
            'leaderNode' => $leaderNode === '' ? null : $leaderNode,
            'leaderRaftAddress' => $leaderRaftAddress === '' ? null : $leaderRaftAddress,
        ];
    }

    /**
     * Replicate a single key. A null value is a tombstone: the sidecar deletes
     * the key and replicates the delete.
     *
     * Only the leader accepts a write. A follower answers 500 with
     * "not the leader" and there is no redirect, so the caller has to give up
     * and let the node that does lead publish the slot.
     *
     * @param  array<string, mixed>|null  $value
     *
     * @throws RaftUnavailableException
     */
    public function set(string $key, ?array $value): void
    {
        $this->send(
            '/set',
            (float) config('ha.raft.timeout.set'),
            fn (PendingRequest $request): Response => $request->post('/set', ['key' => $key, 'value' => $value]),
        );
    }

    /**
     * Every key this node's local FSM holds, decoded back from stored JSON
     * text. Reads are local, so the answer may lag the leader by a moment.
     *
     * @return array<string, array<string, mixed>|null>
     *
     * @throws RaftUnavailableException
     */
    public function getAll(): array
    {
        $payload = $this->send(
            '/get',
            (float) config('ha.raft.timeout.get'),
            fn (PendingRequest $request): Response => $request->get('/get'),
        );

        $data = [];

        foreach ($payload as $key => $stored) {
            $data[(string) $key] = self::decodeStoredValue($stored);
        }

        return $data;
    }

    /**
     * Reverses the sidecar's storage format. Anything that is not an object is
     * read as an absent slot: the log only ever carries slot documents, and a
     * tombstone arrives as null.
     *
     * @return array<string, mixed>|null
     */
    public static function decodeStoredValue(mixed $stored): ?array
    {
        if (is_array($stored)) {
            return $stored;
        }

        if (! is_string($stored)) {
            return null;
        }

        $decoded = json_decode($stored, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  Closure(PendingRequest): Response  $call
     * @param  list<int>  $acceptStatuses
     * @return array<string, mixed>
     *
     * @throws RaftUnavailableException
     */
    private function send(string $endpoint, float $timeout, Closure $call, array $acceptStatuses = [200]): array
    {
        try {
            $response = $call($this->request($timeout));
        } catch (HttpClientException $exception) {
            throw RaftUnavailableException::unreachable($endpoint, $exception);
        }

        if (! in_array($response->status(), $acceptStatuses, true)) {
            throw RaftUnavailableException::badResponse($endpoint, $response->status(), $response->body());
        }

        return $response->json() ?? [];
    }

    private function request(float $timeout): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('ha.raft.url'), '/'))
            ->connectTimeout((float) config('ha.raft.connect_timeout'))
            ->timeout($timeout)
            ->retry(
                (int) config('ha.raft.retry_attempts'),
                (int) config('ha.raft.retry_sleep_milliseconds'),
                $this->shouldRetry(...),
                throw: false,
            )
            ->acceptJson();
    }

    /**
     * A rejected write is the one failure worth giving up on straight away:
     * this node is not the leader and asking it twice will not change that.
     */
    private function shouldRetry(Throwable $exception): bool
    {
        if (! $exception instanceof RequestException) {
            return true;
        }

        return ! RaftUnavailableException::mentionsNotLeader((string) $exception->response?->body());
    }
}
