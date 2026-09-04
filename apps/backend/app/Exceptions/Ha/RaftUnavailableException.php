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
    /**
     * The sidecar rejects a write on a follower with this plain text body and
     * HTTP 500; it is the only way it says "wrong node".
     */
    public const NOT_LEADER_BODY = 'not the leader';

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
        $message = self::mentionsNotLeader($body)
            ? "Raft sidecar refused {$endpoint}: this node is not the leader"
            : "Raft sidecar answered {$status} for {$endpoint}";

        return new self($message, $endpoint, $status, $body);
    }

    public static function mentionsNotLeader(string $body): bool
    {
        return str_contains(strtolower($body), self::NOT_LEADER_BODY);
    }

    /**
     * A write that reached a follower. Publishing has to stop here: there is no
     * redirect to the leader, and the node that leads will publish the slot.
     */
    public function isNotLeader(): bool
    {
        return self::mentionsNotLeader((string) $this->body);
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
