<?php

namespace App\Support;

use App\Enums\EndpointType;
use App\Interfaces\Messageable;
use App\Services\Notification\ChannelRegistry;

/**
 * Snapshot of a message: one canonical body plus the channel specific content
 * the source model produced, keyed by endpoint type value.
 */
class NotifyMessagePayload implements Messageable
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public function __construct(
        private readonly string $body,
        private readonly array $overrides = [],
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function fromBody(string $body, array $overrides = []): self
    {
        return new self($body, $overrides);
    }

    public static function fromMessageable(Messageable $alert): self
    {
        $body = (string) $alert->defaultMessage();
        $overrides = [];

        foreach (app(ChannelRegistry::class)->types() as $type) {
            $content = $alert->messageFor($type);

            if ($content === null) {
                continue;
            }

            if (is_array($content) || (string) $content !== $body) {
                $overrides[$type->value] = is_array($content) ? $content : (string) $content;
            }
        }

        return new self($body, $overrides);
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    public static function fromStored(array $stored): self
    {
        if (array_key_exists('body', $stored)) {
            return new self(
                (string) $stored['body'],
                is_array($stored['overrides'] ?? null) ? $stored['overrides'] : [],
            );
        }

        $body = (string) ($stored['defaultMessage'] ?? '');

        return new self($body, array_filter([
            EndpointType::TELEGRAM->value => self::legacyChatOverride($stored, 'telegram', $body),
            EndpointType::BALE->value => self::legacyChatOverride($stored, 'bale', $body),
            EndpointType::CALL->value => self::legacyCallOverride($stored, $body),
        ], fn (mixed $value): bool => $value !== null));
    }

    public function defaultMessage(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>|string|null
     */
    public function messageFor(EndpointType $type): array|string|null
    {
        return $this->overrides[$type->value] ?? null;
    }

    /**
     * What a channel should send: its own content when the source provided
     * one, the canonical body otherwise.
     *
     * @return array<string, mixed>|string
     */
    public function forChannel(EndpointType $type): array|string
    {
        return $this->messageFor($type) ?? $this->body;
    }

    /**
     * Plain text for the channel, dropping any chat metadata.
     */
    public function textFor(EndpointType $type): string
    {
        $content = $this->forChannel($type);

        return is_array($content) ? (string) ($content['message'] ?? $this->body) : $content;
    }

    /**
     * @return array{body: string, overrides: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'body' => $this->body,
            'overrides' => $this->overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>|string|null
     */
    private static function legacyChatOverride(array $stored, string $key, string $body): array|string|null
    {
        if (! array_key_exists($key, $stored)) {
            return null;
        }

        $content = $stored[$key];

        if (is_array($content)) {
            return $content;
        }

        return (string) $content !== $body ? (string) $content : null;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private static function legacyCallOverride(array $stored, string $body): ?string
    {
        if (! array_key_exists('callMessage', $stored)) {
            return null;
        }

        $call = (string) $stored['callMessage'];

        return $call !== $body ? $call : null;
    }
}
