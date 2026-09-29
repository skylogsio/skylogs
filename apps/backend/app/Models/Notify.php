<?php

namespace App\Models;

use App\Enums\EndpointType;
use App\Interfaces\Messageable;
use App\Support\NotifyMessagePayload;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

class Notify extends BaseModel implements Messageable
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected $casts = [];

    public const STATUS_CREATED = 1;

    public const STATUS_RUNNING = 2;

    public const STATUS_DONE = 3;

    public const STATUS_FAIL = 4;

    public const STATUS_SILENT = 5;

    public const STATUS_ACKNOWLEDGED = 6;

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alertRuleId', '_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'notifyId');
    }

    public function defaultMessage(): string
    {
        return $this->messagePayload()->defaultMessage();
    }

    /**
     * @return array<string, mixed>|string|null
     */
    public function messageFor(EndpointType $type): array|string|null
    {
        return $this->messagePayload()->messageFor($type);
    }

    public function messagePayload(): NotifyMessagePayload
    {
        return NotifyMessagePayload::fromStored(is_array($this->messages) ? $this->messages : []);
    }
}
