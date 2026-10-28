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
     */
    public function show(): JsonResponse
    {
        try {
            return response()->json($this->raft->clusterStatus());
        } catch (RaftUnavailableException $exception) {
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }
}
