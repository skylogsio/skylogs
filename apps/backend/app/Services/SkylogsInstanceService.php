<?php

namespace App\Services;

use App\Models\SkylogsInstance;
use DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Predis\Connection\ConnectionException;
use Queue;

class SkylogsInstanceService
{
    public function isConnected(string $instanceId): bool
    {
        $ds = SkylogsInstance::query()->whereId($instanceId)->firstOrFail();

        try {
            $request = Http::timeout(5);

            $response = $request->get($ds->url);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }

    }

    /**
     * Connection liveness plus that instance's Raft membership.
     *
     * Membership comes from the instance's own `GET /api/v1/ha/cluster/status`.
     * A failed status call leaves the instance connected and the cluster unknown.
     *
     * @return array{isConnected: bool, enabled: bool|null, cluster: array<string, mixed>|null}
     */
    public function connectionStatus(string $instanceId, ?string $bearerToken): array
    {
        if (! $this->isConnected($instanceId)) {
            return $this->withoutCluster(false);
        }

        $instance = SkylogsInstance::query()->whereId($instanceId)->firstOrFail();

        try {
            $pendingRequest = Http::timeout(5)->acceptJson();

            if (is_string($bearerToken) && $bearerToken !== '') {
                $pendingRequest = $pendingRequest->withToken($bearerToken);
            }

            $response = $pendingRequest->get($this->clusterStatusUrl($instance));
        } catch (\Exception $e) {
            return $this->withoutCluster(true);
        }

        if (! $response->successful()) {
            return $this->withoutCluster(true);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return $this->withoutCluster(true);
        }

        $cluster = $payload['cluster'] ?? null;

        return [
            'isConnected' => true,
            'enabled' => array_key_exists('enabled', $payload) ? (bool) $payload['enabled'] : null,
            'cluster' => is_array($cluster) ? $cluster : null,
        ];
    }

    private function clusterStatusUrl(SkylogsInstance $instance): string
    {
        return rtrim($instance->getBaseUrl(), '/').'/api/v1/ha/cluster/status';
    }

    /**
     * @return array{isConnected: bool, enabled: null, cluster: null}
     */
    private function withoutCluster(bool $isConnected): array
    {
        return [
            'isConnected' => $isConnected,
            'enabled' => null,
            'cluster' => null,
        ];
    }

    public static function CheckWorkers(): bool
    {
        try {
            return Queue::size() < 60;
        } catch (\Exception $exception) {
            return false;
        }
    }

    public static function CheckRedis(): bool
    {
        try {
            Redis::ping();

            return true;
        } catch (ConnectionException $e) {
            return false;
        }
    }

    public static function CheckDatabase(): bool
    {
        try {

            DB::connection()->getMongoClient()->listDatabases();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
