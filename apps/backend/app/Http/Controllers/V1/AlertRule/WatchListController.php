<?php

namespace App\Http\Controllers\V1\AlertRule;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlertRule\IndexWatchListRequest;
use App\Http\Resources\AlertRule\WatchListItemResource;
use App\Http\Resources\PaginatedJson;
use App\Models\AlertRule;
use App\Services\AlertRuleWatchListService;
use Illuminate\Http\JsonResponse;

class WatchListController extends Controller
{
    public function __construct(private readonly AlertRuleWatchListService $watchListService) {}

    public function index(IndexWatchListRequest $request): JsonResponse
    {
        $paginator = $this->watchListService->paginateForUser(
            $request->user(),
            (int) ($request->validated('perPage') ?? 25),
            $request->url(),
        );

        return PaginatedJson::make($paginator, WatchListItemResource::class);
    }

    public function toggle(string $id): JsonResponse
    {
        $alert = AlertRule::query()->where('_id', $id)->firstOrFail();
        $isWatched = $this->watchListService->toggle(auth()->user(), $alert);

        return response()->json([
            'status' => true,
            'isWatched' => $isWatched,
        ]);
    }
}
