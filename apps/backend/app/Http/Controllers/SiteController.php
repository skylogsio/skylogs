<?php

namespace App\Http\Controllers;

use App\Services\SkylogsInstanceService;
use Illuminate\Http\Request;

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
            'redis' => SkylogsInstanceService::CheckRedis(),
            'database' => SkylogsInstanceService::CheckDatabase(),
            'workers' => SkylogsInstanceService::CheckWorkers(),
        ]);

        $isHealthy = $statuses->every(fn ($service) => $service === true);

        if ($isHealthy) {
            return response()->json(['status' => true]);
        } else {
            abort(500, 'error');
        }

    }

    public function LeaderPing(Request $request)
    {
        $priority = $request->priority;
        SkylogsInstanceService::UpdateLastLeaderPing($priority);

        return response()->json(['status' => true]);
    }
}
