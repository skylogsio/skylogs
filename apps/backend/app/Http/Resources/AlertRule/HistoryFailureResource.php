<?php

namespace App\Http\Resources\AlertRule;

use App\Models\NotificationDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationDelivery
 */
class HistoryFailureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'endpointId' => $this->endpointId,
            'endpointName' => $this->endpointName,
            'endpointType' => $this->endpointType,
            'lastError' => $this->lastError,
        ];
    }
}
