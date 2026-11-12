<?php

namespace App\Services\Notification;

use App\Interfaces\NotificationProvider;
use InvalidArgumentException;

class NotificationProviderRegistry
{
    /**
     * @var array<string, NotificationProvider>
     */
    private array $providers = [];

    /**
     * @param  iterable<NotificationProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->key()] = $provider;
        }
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function for(string $key): NotificationProvider
    {
        return $this->providers[$key]
            ?? throw new InvalidArgumentException("No notification provider registered for [{$key}].");
    }
}
