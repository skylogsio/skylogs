<?php

namespace App\Services\Ha;

use App\Exceptions\Ha\RaftUnavailableException;
use Closure;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the Raft sidecar running next to this node.
 *
 * The sidecar owns leadership and the replicated log; this class only speaks
 * its three HTTP endpoints and translates every failure into a
 * RaftUnavailableException so callers never have to handle HTTP errors.
 */
class RaftClient
{
    /**
     * @return array{isLeader: bool, nodeId: string, leaderId: string|null, leaderAddress: string|null, term: int|null}
     *
     * @throws RaftUnavailableException
     */
    public function leader(): array
    {
        $payload = $this->send(
            '/leader',
            (float) config('ha.raft.timeout.leader'),
            fn (PendingRequest $request): Response => $request->get('/leader'),
        );

        return [
            'isLeader' => (bool) ($payload['isLeader'] ?? false),
            'nodeId' => (string) ($payload['nodeId'] ?? ''),
            'leaderId' => $payload['leaderId'] ?? null,
            'leaderAddress' => $payload['leaderAddress'] ?? null,
            'term' => isset($payload['term']) ? (int) $payload['term'] : null,
        ];
    }

    /**
     * Replicate a single key. A null value is a tombstone: the sidecar deletes
     * the key and replicates the delete.
     *
     * @param  array<string, mixed>|null  $value
     * @return array{ok: bool, index: int|null}
     *
     * @throws RaftUnavailableException
     */
    public function save(string $key, ?array $value): array
    {
        $payload = $this->send(
            '/save',
            (float) config('ha.raft.timeout.save'),
            fn (PendingRequest $request): Response => $request->post('/save', [$key => $value]),
        );

        return [
            'ok' => (bool) ($payload['ok'] ?? false),
            'index' => isset($payload['index']) ? (int) $payload['index'] : null,
        ];
    }

    /**
     * @return array{index: int|null, data: array<string, array<string, mixed>>}
     *
     * @throws RaftUnavailableException
     */
    public function state(): array
    {
        $payload = $this->send(
            '/state',
            (float) config('ha.raft.timeout.state'),
            fn (PendingRequest $request): Response => $request->get('/state'),
        );

        return [
            'index' => isset($payload['index']) ? (int) $payload['index'] : null,
            'data' => $payload['data'] ?? [],
        ];
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
                throw: false,
            )
            ->acceptJson();
    }
}
