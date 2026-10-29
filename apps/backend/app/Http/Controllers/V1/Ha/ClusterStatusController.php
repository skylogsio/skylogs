<?php

namespace App\Http\Controllers\V1\Ha;

use App\Exceptions\Ha\RaftUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\Ha\RaftClient;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ClusterStatusController extends Controller
{
    public function __construct(private readonly RaftClient $raft) {}

    /**
     * Proxies the local Raft sidecar's GET /cluster/status for the frontend.
     *
     * A single-node install leaves HA_ENABLED false, so there is no sidecar to
     * call. That is a configured state, not an outage.
     */
    public function show(): JsonResponse
    {
        if (! config('ha.enabled')) {
            return response()->json([
                'enabled' => false,
                'cluster' => null,
            ]);
        }

        try {
            return response()->json([
                'enabled' => true,
                'cluster' => $this->raft->clusterStatus(),
            ]);
        } catch (RaftUnavailableException $exception) {
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }
}
