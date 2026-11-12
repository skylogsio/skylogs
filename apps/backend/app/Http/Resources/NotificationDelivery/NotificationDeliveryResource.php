<?php

namespace App\Http\Resources\NotificationDelivery;

use App\Models\NotificationDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationDelivery
 */
class NotificationDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notifyId' => $this->notifyId,
            'source' => $this->source,
            'sourceId' => $this->sourceId,
            'flowEndpointId' => $this->flowEndpointId,
            'flowStepIndex' => $this->flowStepIndex,
            'endpointId' => $this->endpointId,
            'endpointType' => $this->endpointType,
            'endpointName' => $this->endpointName,
            'userId' => $this->userId,
            'message' => $this->message,
            'templateApplied' => (bool) $this->templateApplied,
            'status' => $this->status?->value,
            'attempts' => (int) $this->attempts,
            'maxAttempts' => (int) $this->maxAttempts,
            'canRetry' => $this->isFailed(),
            'nextRetryAt' => $this->nextRetryAt,
            'lastError' => $this->lastError,
            'attemptLog' => $this->attemptLog ?? [],
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
