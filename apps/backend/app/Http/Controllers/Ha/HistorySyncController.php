<?php

namespace App\Http\Controllers\Ha;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ha\HaHistorySyncRequest;
use App\Http\Resources\Ha\HaHistoryPageResource;
use App\Services\Ha\HaHistorySyncService;
use App\Services\Ha\HaLeaderService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class HistorySyncController extends Controller
{
    public function __construct(
        private readonly HaHistorySyncService $sync,
        private readonly HaLeaderService $leader,
    ) {}

    /**
     * Called by followers to pull one page of history or notify documents.
     */
    public function show(HaHistorySyncRequest $request): HaHistoryPageResource
    {
        if (! $this->leader->isLeader()) {
            throw new ConflictHttpException('This node is not the leader.');
        }

        return new HaHistoryPageResource(
            $this->sync->page(
                $request->collectionAlias(),
                $request->afterUpdatedAt(),
                $request->afterId(),
                $request->limit(),
            )
        );
    }
}
