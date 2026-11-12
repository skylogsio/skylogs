<?php

namespace App\Http\Controllers\Ha;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ha\ApplyHaStateRequest;
use App\Services\Ha\HaStateApplier;
use Illuminate\Http\JsonResponse;

class StateController extends Controller
{
    public function __construct(private readonly HaStateApplier $applier) {}

    /**
     * Called by this node's own Raft sidecar once the leader's write has been
     * committed to the replicated log.
     */
    public function apply(ApplyHaStateRequest $request): JsonResponse
    {
        return response()->json(
            $this->applier->apply($request->stateKey(), $request->stateValue())
        );
    }
}
