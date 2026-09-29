<?php

namespace App\Services\Notification;

use App\Enums\EndpointType;
use App\Interfaces\NotificationChannel;
use InvalidArgumentException;

class ChannelRegistry
{
    /**
     * @var array<string, NotificationChannel>
     */
    private array $channels = [];

    /**
     * @param  iterable<NotificationChannel>  $channels
     */
    public function __construct(iterable $channels)
    {
        foreach ($channels as $channel) {
            $this->channels[$channel->type()->value] = $channel;
        }
    }

    public function has(string|EndpointType $type): bool
    {
        return isset($this->channels[$this->key($type)]);
    }

    public function for(string|EndpointType $type): NotificationChannel
    {
        return $this->channels[$this->key($type)]
            ?? throw new InvalidArgumentException("No notification channel registered for endpoint type [{$this->key($type)}].");
    }

    /**
     * @return array<string, NotificationChannel>
     */
    public function all(): array
    {
        return $this->channels;
    }

    /**
     * @return list<EndpointType>
     */
    public function types(): array
    {
        return array_values(array_map(
            fn (NotificationChannel $channel): EndpointType => $channel->type(),
            $this->channels,
        ));
    }

    /**
     * @return list<string>
     */
    public function typeValues(): array
    {
        return array_keys($this->channels);
    }

    /**
     * @return list<string>
     */
    public function verifiableTypeValues(): array
    {
        return array_keys(array_filter(
            $this->channels,
            fn (NotificationChannel $channel): bool => $channel->requiresVerification(),
        ));
    }

    private function key(string|EndpointType $type): string
    {
        return $type instanceof EndpointType ? $type->value : $type;
    }
}
