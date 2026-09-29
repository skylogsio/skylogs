<?php

namespace App\Models;

use App\Observers\EndpointObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

/**
 * Where a notification is sent. `type` is an App\Enums\EndpointType value;
 * which other fields are stored depends on the type's notification channel.
 */
#[ObservedBy(EndpointObserver::class)]
class Endpoint extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'onCall' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alertRules()
    {
        return $this->belongsToMany(AlertRule::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'endpointId');
    }
}
