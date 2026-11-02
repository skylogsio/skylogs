<?php

namespace Tests\Support\Messageables;

use App\Concerns\ProvidesChannelMessages;
use App\Interfaces\Messageable;

/**
 * Minimal Messageable used in unit tests (no DB, no Laravel models).
 */
final class PlainTextMessageable implements Messageable
{
    use ProvidesChannelMessages;

    public function __construct(
        private readonly string $text = 'plain-body',
    ) {}

    public function defaultMessage(): string
    {
        return $this->text;
    }
}
