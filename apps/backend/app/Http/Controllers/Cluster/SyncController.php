<?php

namespace App\Http\Controllers\Cluster;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClusterService;

class SyncController extends Controller
{
    public function __construct(protected ClusterService $clusterService) {}

    public function Data()
    {

        $users = User::with(['roles', 'endpoints'])->get()->makeVisible('password');

        return response()->json($users);

    }
}
