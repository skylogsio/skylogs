<?php

namespace App\Http\Controllers;

use App\Services\SkylogsInstanceService;

class SiteController extends Controller
{
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
}
