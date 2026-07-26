<?php

namespace App\Services\Ha;

use App\Exceptions\Ha\LeaderUnavailableException;
use App\Http\Middleware\HaNodeAuth;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;

/**
 * Pulls a configuration snapshot from the leader's backend.
 *
 * The address comes from the local sidecar rather than configuration, so a
 * follower always asks whichever node currently holds leadership and never a
 * load balancer that might route the request back to itself.
 */
class LeaderConfigClient
{
    /**
     * @return array{version: int, changed: bool, collections: array<string, mixed>}
     *
     * @throws LeaderUnavailableException
     */
    public function snapshot(string $address, int $since): array
    {
        $url = rtrim($address, '/').'/api/ha/config-sync';

        try {
            $response = Http::connectTimeout((float) config('ha.config_sync.connect_timeout'))
                ->timeout((float) config('ha.config_sync.timeout'))
                ->withHeader(HaNodeAuth::SECRET_HEADER, (string) config('ha.node_secret'))
                ->acceptJson()
                ->get($url, ['since' => $since]);
        } catch (HttpClientException $exception) {
            throw LeaderUnavailableException::unreachable($address, $exception);
        }

        if ($response->failed()) {
            throw LeaderUnavailableException::badResponse($address, $response->status());
        }

        $payload = $response->json() ?? [];

        return [
            'version' => (int) ($payload['version'] ?? 0),
            'changed' => (bool) ($payload['changed'] ?? false),
            'collections' => is_array($payload['collections'] ?? null) ? $payload['collections'] : [],
        ];
    }
}
