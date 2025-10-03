<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Jobs\RefreshPrometheusCheckJob;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\SkylogsInstance;
use App\Services\ApiService;
use App\Services\SkylogsInstanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;


class SiteController extends Controller
{

    /*    public function Health()
        {
            $isLeader = SkylogsInstanceService::isLeader();

            if ($isLeader) {
                return response()->json(["status" => true]);
            } else {
                abort(500,"error");
            }

        }
       */
    public function Health()
    {

        $statuses = collect([
            "redis" => SkylogsInstanceService::CheckRedis(),
            "database" => SkylogsInstanceService::CheckDatabase(),
            "workers" => SkylogsInstanceService::CheckWorkers(),
        ]);

        $isHealthy = $statuses->every(fn ($service) => $service === true);

        if ($isHealthy) {
            return response()->json(["status" => true]);
        } else {
            abort(500, "error");
        }

    }


    public function LeaderPing(Request $request)
    {
        $priority = $request->priority;
        SkylogsInstanceService::UpdateLastLeaderPing($priority);
        return response()->json(["status" => true]);
    }


}
