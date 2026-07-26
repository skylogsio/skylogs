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
 * The sidecar owns leadership and the replicated log; this class only speaks
 * its three HTTP endpoints and translates every failure into a
 * RaftUnavailableException so callers never have to handle HTTP errors.
 *
 * A value is stored as the raw JSON text of whatever was sent, so a slot goes
 * out as an object and comes back as a string: every read decodes.
 */
class RaftClient
{
    /**
     * How the sidecar sees the cluster from this node. /status answers 200
     * whatever role the node holds, so a follower is an answer rather than a
     * failure, and the leader is named by its Raft address, never by a URL.
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

        $leaderRaftAddress = (string) ($payload['leader'] ?? '');

        return [
            'isLeader' => (bool) ($payload['is_leader'] ?? false),
            'nodeId' => (string) ($payload['node_id'] ?? ''),
            'leaderRaftAddress' => $leaderRaftAddress === '' ? null : $leaderRaftAddress,
            'state' => isset($payload['state']) ? (string) $payload['state'] : null,
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
     * @return array<string, mixed>
     *
     * @throws RaftUnavailableException
     */
    private function send(string $endpoint, float $timeout, Closure $call): array
    {
        try {
            $response = $call($this->request($timeout));
        } catch (HttpClientException $exception) {
            throw RaftUnavailableException::unreachable($endpoint, $exception);
        }

        if ($response->failed()) {
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
