<?php

namespace App\Exceptions\Ha;

use Exception;
use Throwable;

/**
 * Raised for every failed Raft sidecar interaction so that alert evaluation
 * never sees a raw HTTP exception.
 */
class RaftUnavailableException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $endpoint,
        public readonly ?int $status = null,
        public readonly ?string $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    public static function unreachable(string $endpoint, Throwable $previous): self
    {
        return new self(
            "Raft sidecar is unreachable at {$endpoint}: {$previous->getMessage()}",
            $endpoint,
            null,
            null,
            $previous,
        );
    }

    public static function badResponse(string $endpoint, int $status, string $body): self
    {
        return new self(
            "Raft sidecar answered {$status} for {$endpoint}",
            $endpoint,
            $status,
            $body,
        );
    }

    /**
     * @return array{endpoint: string, status: int|null, body: string|null}
     */
    public function context(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'status' => $this->status,
            'body' => $this->body,
        ];
    }
}
