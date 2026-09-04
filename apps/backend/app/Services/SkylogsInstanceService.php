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
