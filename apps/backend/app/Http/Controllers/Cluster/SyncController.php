<?php

namespace App\Http\Controllers\Cluster;


use App\Enums\DataSourceType;
use App\Http\Controllers\Controller;
use App\Models\DataSource\DataSource;
use App\Models\Endpoint;
use App\Models\Service;
use App\Models\User;
use App\Services\ClusterService;
use App\Services\DataSourceService;
use Illuminate\Http\Request;


class SyncController extends Controller
{

    public function __construct(protected ClusterService $clusterService){}

    public function Data()
    {

        $users = User::with(["roles",'endpoints'])->get()->makeVisible("password");
        return response()->json($users);

    }



}
