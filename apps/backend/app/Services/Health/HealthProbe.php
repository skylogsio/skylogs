<?php

namespace App\Services\Health;

use Closure;
use Illuminate\Http\Client\Response;

final class HealthProbe
{
    /**
     * Hard cap so one down or slow URL cannot hold the 10 second health run.
     */
    public const MAX_TIMEOUT_SECONDS = 5;

    public const CONNECT_TIMEOUT_SECONDS = 2;

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $json
     * @param  Closure(Response): (?string)|null  $evaluate
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public ?string $username = null,
        public ?string $password = null,
        public ?string $bearerToken = null,
        public ?array $json = null,
        public ?string $body = null,
        public int $timeoutSeconds = 5,
        public bool $verifyTls = true,
        public ?Closure $evaluate = null,
    ) {}

    public function effectiveTimeout(): int
    {
        return min(self::MAX_TIMEOUT_SECONDS, max(1, $this->timeoutSeconds));
    }

    public function errorFor(Response $response): ?string
    {
        if ($this->evaluate instanceof Closure) {
            return ($this->evaluate)($response);
        }

        return in_array($response->status(), [200, 201], true)
            ? null
            : 'HTTP '.$response->status();
    }

    public static function httpStatusError(Response $response): ?string
    {
        return $response->successful() ? null : 'HTTP '.$response->status();
    }
}
