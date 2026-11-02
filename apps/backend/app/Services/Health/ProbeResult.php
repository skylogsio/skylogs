<?php

namespace App\Services\Health;

final class ProbeResult
{
    public function __construct(
        public bool $ok,
        public ?string $error,
        public ?int $statusCode,
        public ?int $latencyMs,
        public ?string $url,
    ) {}

    public static function success(?int $statusCode, ?int $latencyMs, ?string $url): self
    {
        return new self(true, null, $statusCode, $latencyMs, $url);
    }

    public static function failure(string $error, ?string $url = null, ?int $statusCode = null, ?int $latencyMs = null): self
    {
        return new self(false, $error, $statusCode, $latencyMs, $url);
    }
}
