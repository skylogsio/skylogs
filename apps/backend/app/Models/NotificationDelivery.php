<?php

namespace App\Models;

use App\Enums\NotificationDeliveryStatus;
use App\Support\NotifyMessagePayload;
use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * One message to one endpoint, with every attempt made to deliver it.
 *
 * The rendered message is stored so a retry sends exactly the same text; the
 * endpoint itself is reloaded on every attempt so secrets are never copied.
 */
class NotificationDelivery extends BaseModel
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory;

    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    public const SOURCE_ALERT = 'alert';

    public const SOURCE_INCIDENT_POLICY = 'incident_policy';

    public const SOURCE_FLOW = 'flow';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NotificationDeliveryStatus::class,
            'attempts' => 'integer',
            'maxAttempts' => 'integer',
            'templateApplied' => 'boolean',
            'nextRetryAt' => 'datetime',
        ];
    }

    public function notify(): BelongsTo
    {
        return $this->belongsTo(Notify::class, 'notifyId', '_id');
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class, 'endpointId', '_id');
    }

    public function messagePayload(): NotifyMessagePayload
    {
        return NotifyMessagePayload::fromStored(is_array($this->message) ? $this->message : []);
    }

    public function hasAttemptsLeft(): bool
    {
        return (int) $this->attempts < (int) $this->maxAttempts;
    }

    public function isFailed(): bool
    {
        return $this->status === NotificationDeliveryStatus::FAILED;
    }
}
