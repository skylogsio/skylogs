<?php

namespace App\Exceptions\Ha;

use Exception;
use Throwable;

/**
 * Raised when a follower cannot pull configuration from the leader.
 *
 * Separate from RaftUnavailableException because the two say different things:
 * one means the local sidecar is sick, this one means the peer is.
 */
class LeaderUnavailableException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $address = null,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    public static function unknownAddress(): self
    {
        return new self(
            'No backend URL is known for the current leader: either an election is in progress, or the leader is missing from HA_PEER_URLS.'
        );
    }

    public static function unreachable(string $address, Throwable $previous): self
    {
        return new self(
            "The leader at {$address} is unreachable: {$previous->getMessage()}",
            $address,
            null,
            $previous,
        );
    }

    public static function badResponse(string $address, int $status): self
    {
        return new self("The leader at {$address} answered {$status}.", $address, $status);
    }

    /**
     * @return array{address: string|null, status: int|null}
     */
    public function context(): array
    {
        return [
            'address' => $this->address,
            'status' => $this->status,
        ];
    }
}
