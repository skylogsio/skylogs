<?php

namespace App\Services;

use App\Helpers\Constants;
use App\Models\SkylogsInstance;
use DB;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
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

    public static function SendPing()
    {
        $serverPriority = self::GetServerPriority();
        $instances = SkylogsInstance::whereNotIn('priority', [$serverPriority])->get();
        $urls = [];

        foreach ($instances as $instance) {
            $urls[] = $instance->getPingUrl();
        }

        $result = Http::pool(function (Pool $pool) use ($urls, $serverPriority) {
            $result = [];

            foreach ($urls as $url) {
                $result[] = $pool->get($url, ['priority' => $serverPriority]);
            }

            return $result;
        });

    }

    public static function GetServerPriority()
    {
        $serverPriority = intval(config('variables.priority'));

        return $serverPriority;
    }

    public static function SetServerPriority(?SkylogsInstance $instance = null)
    {
        if (empty($instance)) {
            $serverPriority = intval(config('variables.priority'));
        } else {
            $serverPriority = $instance->priority;
        }

        $leaderPriority = \Cache::store('database')->delete(Constants::LEADER_PRIORITY);
        $leaderPriority = \Cache::store('database')->put(Constants::LEADER_PRIORITY, $serverPriority);

    }

    public static function GetLeaderPriority()
    {
        return \Cache::store('database')->get(Constants::LEADER_PRIORITY, PHP_INT_MAX);
    }

    public static function isLeader(SkylogsInstance $instance): bool
    {

        $serverPriority = empty($instance) ? self::getServerPriority() : $instance->priority;

        $leaderPriority = self::GetLeaderPriority();

        return $leaderPriority == $serverPriority;
    }

    public static function CheckLeaderPing()
    {
        $isLeader = self::isLeader();
        $serverPriority = self::getServerPriority();
        if (! $isLeader) {
            $lastLeaderPing = \Cache::get(Constants::LAST_LEADER_PING, time());
            if ($lastLeaderPing < time() - ($serverPriority * 10)) {
                self::SetServerPriority();
            }

        }
    }

    public static function UpdateLastLeaderPing($priority)
    {

        //        if ($priority < self::GetServerPriority()) {
        //            \Cache::put(Constants::LAST_LEADER_PING."_".$priority, time());
        \Cache::put(Constants::LAST_LEADER_PING, time());
        //        }

    }

    public static function getHealthCheck(SkylogsInstance $instance)
    {
        try {
            $request = \Http::acceptJson();
            if (! empty($instance->username) && ! empty($instance->password)) {
                $request = $request->withBasicAuth($instance->username, $instance->password);
            }
            $response = $request->timeout(5)->get($instance->getHealthUrl());

            return $response->status() == 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function GetPriorities()
    {
        $instances = SkylogsInstance::all()->pluck('priority');

        return $instances;
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
            \Illuminate\Support\Facades\Redis::ping();

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
