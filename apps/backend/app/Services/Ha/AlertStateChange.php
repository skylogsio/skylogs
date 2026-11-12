<?php

namespace App\Services\Ha;

/**
 * One slot of replicated state, as produced by a projector: either the current
 * value of that slot or a tombstone that removes it from the replicated log.
 */
final class AlertStateChange
{
    /**
     * @param  array<string, mixed>|null  $value
     */
    public function __construct(
        public readonly AlertStateKey $key,
        public readonly ?array $value,
    ) {}

    public static function tombstone(AlertStateKey $key): self
    {
        return new self($key, null);
    }

    public function isTombstone(): bool
    {
        return $this->value === null;
    }
}
