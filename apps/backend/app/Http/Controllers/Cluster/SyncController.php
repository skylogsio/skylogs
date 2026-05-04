<?php

namespace App\Http\Controllers\Cluster;

use App\Http\Controllers\Controller;
use App\Services\ClusterService;

class SyncController extends Controller
{
    public function __construct(protected ClusterService $clusterService) {}

    public function Data()
    {

        return response()->json($this->clusterService->getSyncData());

    }
}
