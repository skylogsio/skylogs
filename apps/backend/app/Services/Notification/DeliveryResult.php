<?php

namespace App\Services\Notification;

use App\Enums\NotificationDeliveryStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Outcome of one attempt to deliver a message to one endpoint, already
 * interpreted by the channel that made the request.
 */
final class DeliveryResult
{
    private function __construct(
        public readonly NotificationDeliveryStatus $status,
        public readonly bool $retryable,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerMessageId = null,
        public readonly mixed $response = null,
        public readonly ?string $error = null,
    ) {}

    public static function sent(?int $httpStatus = null, mixed $response = null, ?string $providerMessageId = null): self
    {
        return new self(NotificationDeliveryStatus::SENT, false, $httpStatus, $providerMessageId, $response);
    }

    public static function failed(string $error, ?int $httpStatus = null, mixed $response = null): self
    {
        return new self(NotificationDeliveryStatus::FAILED, false, $httpStatus, null, $response, $error);
    }

    public static function retryableFailure(string $error, ?int $httpStatus = null, mixed $response = null): self
    {
        return new self(NotificationDeliveryStatus::FAILED, true, $httpStatus, null, $response, $error);
    }

    /**
     * Network and mail transport errors are worth retrying; anything else is
     * most likely a bug or bad configuration that another attempt will not fix.
     */
    public static function fromException(Throwable $exception): self
    {
        $retryable = $exception instanceof ConnectionException
            || $exception instanceof TransportExceptionInterface;

        return new self(NotificationDeliveryStatus::FAILED, $retryable, error: $exception->getMessage());
    }

    /**
     * Judges a webhook-style response by HTTP status alone: 2xx is sent,
     * 429 and 5xx are retryable, every other status is a permanent failure.
     */
    public static function fromHttpStatus(Response $response): self
    {
        $body = self::responseBody($response);

        if ($response->successful()) {
            return self::sent($response->status(), $body);
        }

        $error = 'HTTP '.$response->status();

        return self::isRetryableStatus($response->status())
            ? self::retryableFailure($error, $response->status(), $body)
            : self::failed($error, $response->status(), $body);
    }

    public static function isRetryableStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    /**
     * Decoded JSON when the provider sent JSON, the raw text otherwise, and
     * null for empty bodies (Teams and Discord answer with nothing).
     */
    public static function responseBody(Response $response): mixed
    {
        $json = $response->json();

        if ($json !== null) {
            return $json;
        }

        $body = $response->body();

        return $body === '' ? null : $body;
    }

    public function isSent(): bool
    {
        return $this->status === NotificationDeliveryStatus::SENT;
    }

    /**
     * @return array{status: string, retryable: bool, httpStatus: int|null, providerMessageId: string|null, response: mixed, error: string|null}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'retryable' => $this->retryable,
            'httpStatus' => $this->httpStatus,
            'providerMessageId' => $this->providerMessageId,
            'response' => self::truncate($this->response),
            'error' => $this->error === null ? null : Str::limit($this->error, self::maxLength()),
        ];
    }

    private static function truncate(mixed $response): mixed
    {
        if ($response === null) {
            return null;
        }

        $encoded = is_string($response) ? $response : json_encode($response);

        if ($encoded === false || mb_strlen($encoded) <= self::maxLength()) {
            return $response;
        }

        return Str::limit($encoded, self::maxLength());
    }

    private static function maxLength(): int
    {
        return (int) config('notification.response_max_length', 4096);
    }
}
