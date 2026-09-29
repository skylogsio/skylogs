<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationDelivery\IndexNotificationDeliveryRequest;
use App\Http\Resources\NotificationDelivery\NotificationDeliveryResource;
use App\Http\Resources\PaginatedJson;
use App\Models\NotificationDelivery;
use App\Services\Notification\NotificationDeliveryService;
use Illuminate\Http\JsonResponse;

class NotificationDeliveryController extends Controller
{
    public function __construct(private readonly NotificationDeliveryService $deliveryService) {}

    public function index(IndexNotificationDeliveryRequest $request): JsonResponse
    {
        $query = NotificationDelivery::query();

        foreach (['notifyId', 'endpointId', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->validated($filter));
            }
        }

        $this->deliveryService->applyVisibility($query, auth()->user());

        $paginator = $query->orderByDesc('createdAt')->paginate((int) ($request->validated('perPage') ?? 25));

        return PaginatedJson::make($paginator, NotificationDeliveryResource::class);
    }

    public function show(string $id): NotificationDeliveryResource
    {
        $delivery = NotificationDelivery::query()->where('_id', $id)->firstOrFail();

        abort_unless($this->deliveryService->canView(auth()->user(), $delivery), 403);

        return new NotificationDeliveryResource($delivery);
    }
}
