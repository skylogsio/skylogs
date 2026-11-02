<?php

namespace Database\Factories;

use App\Enums\EndpointType;
use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use MongoDB\BSON\ObjectId;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notifyId' => null,
            'source' => NotificationDelivery::SOURCE_ALERT,
            'sourceId' => null,
            'endpointId' => (string) new ObjectId,
            'endpointType' => EndpointType::TELEGRAM->value,
            'endpointName' => fake()->words(2, true),
            'userId' => (string) new ObjectId,
            'message' => [
                'body' => fake()->sentence(),
                'overrides' => [],
            ],
            'templateApplied' => false,
            'status' => NotificationDeliveryStatus::PENDING,
            'attempts' => 0,
            'maxAttempts' => 3,
            'nextRetryAt' => null,
            'lastError' => null,
            'attemptLog' => [],
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationDeliveryStatus::SENT,
            'attempts' => 1,
            'attemptLog' => [[
                'attempt' => 1,
                'at' => now()->toIso8601String(),
                'status' => NotificationDeliveryStatus::SENT->value,
                'retryable' => false,
                'httpStatus' => 200,
                'providerMessageId' => '1',
                'response' => ['ok' => true],
                'error' => null,
                'durationMs' => 12,
                'trigger' => 'auto',
            ]],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationDeliveryStatus::FAILED,
            'attempts' => $attributes['maxAttempts'] ?? 3,
            'lastError' => 'HTTP 502',
        ]);
    }
}
