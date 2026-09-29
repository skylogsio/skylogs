<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationDelivery\NotificationDeliveryResource;
use App\Models\NotificationDelivery;
use App\Services\Notification\NotificationDeliveryService;

class RetryNotificationDeliveryController extends Controller
{
    public function __construct(private readonly NotificationDeliveryService $deliveryService) {}

    public function __invoke(string $id): NotificationDeliveryResource
    {
        $delivery = NotificationDelivery::query()->where('_id', $id)->firstOrFail();

        abort_unless($this->deliveryService->canView(auth()->user(), $delivery), 403);
        abort_unless($delivery->isFailed(), 422, 'Only failed deliveries can be retried.');

        return new NotificationDeliveryResource($this->deliveryService->retry($delivery));
    }
}
