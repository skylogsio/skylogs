<?php

namespace App\Support;

use App\Concerns\ProvidesDefaultChannelMessages;
use App\Interfaces\Messageable;

class NotifyMessagePayload implements Messageable
{
    use ProvidesDefaultChannelMessages {
        telegram as protected defaultTelegram;
        baleMessage as protected defaultBaleMessage;
        callMessage as protected defaultCallMessage;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function __construct(
        private readonly string $body,
        private readonly array $overrides = [],
    ) {}

    public static function fromBody(string $body, array $overrides = []): self
    {
        return new self($body, $overrides);
    }

    public static function fromMessageable(Messageable $alert): self
    {
        $body = (string) $alert->defaultMessage();
        $overrides = [];

        $telegram = $alert->telegram();
        if (is_array($telegram)) {
            $overrides['telegram'] = $telegram;
        } elseif ((string) $telegram !== $body) {
            $overrides['telegram'] = (string) $telegram;
        }

        $bale = $alert->baleMessage();
        if (is_array($bale)) {
            $overrides['bale'] = $bale;
        } elseif ((string) $bale !== $body) {
            $overrides['bale'] = (string) $bale;
        }

        $call = (string) $alert->callMessage();
        if ($call !== $body) {
            $overrides['call'] = $call;
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
            'telegram' => self::legacyTelegramOverride($stored, $body),
            'bale' => self::legacyBaleOverride($stored, $body),
            'call' => self::legacyCallOverride($stored, $body),
        ], fn (mixed $value): bool => $value !== null));
    }

    public function defaultMessage(): string
    {
        return $this->body;
    }

    public function telegram(): array|string
    {
        return $this->overrides['telegram'] ?? $this->defaultTelegram();
    }

    public function baleMessage(): array|string
    {
        return $this->overrides['bale'] ?? $this->defaultBaleMessage();
    }

    public function callMessage(): string
    {
        return (string) ($this->overrides['call'] ?? $this->defaultCallMessage());
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
     */
    private static function legacyTelegramOverride(array $stored, string $body): array|string|null
    {
        if (! array_key_exists('telegram', $stored)) {
            return null;
        }

        $telegram = $stored['telegram'];

        if (is_array($telegram)) {
            return $telegram;
        }

        return (string) $telegram !== $body ? (string) $telegram : null;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private static function legacyBaleOverride(array $stored, string $body): array|string|null
    {
        if (! array_key_exists('bale', $stored)) {
            return null;
        }

        $bale = $stored['bale'];

        if (is_array($bale)) {
            return $bale;
        }

        return (string) $bale !== $body ? (string) $bale : null;
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
