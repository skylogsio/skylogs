<?php

namespace App\Http\Controllers\Ha;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ha\HaConfigSyncRequest;
use App\Http\Resources\Ha\HaConfigSnapshotResource;
use App\Services\Ha\HaConfigSyncService;
use App\Services\Ha\HaLeaderService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ConfigSyncController extends Controller
{
    public function __construct(
        private readonly HaConfigSyncService $sync,
        private readonly HaLeaderService $leader,
    ) {}

    /**
     * Called by followers to pull the leader's configuration.
     */
    public function show(HaConfigSyncRequest $request): HaConfigSnapshotResource
    {
        /*
         | Only the leader may answer. A follower's own copy can be a full
         | interval behind, and serving it would let stale configuration
         | propagate sideways between followers and outlive the leader that
         | replaced it.
         */
        if (! $this->leader->isLeader()) {
            throw new ConflictHttpException('This node is not the leader.');
        }

        return new HaConfigSnapshotResource($this->sync->snapshot($request->since()));
    }
}
