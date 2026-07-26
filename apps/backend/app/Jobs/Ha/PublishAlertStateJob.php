<?php

namespace App\Jobs\Ha;

use App\Exceptions\Ha\RaftUnavailableException;
use App\Queue\Middleware\EnsureLeader;
use App\Services\Ha\RaftClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes one replicated slot to the Raft sidecar.
 *
 * Queued rather than inline so that a slow or unreachable sidecar can never
 * delay, and never fail, the alert evaluation that produced the change.
 */
class PublishAlertStateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'ha';

    public int $tries = 5;

    /**
     * @param  array<string, mixed>|null  $value  A null value is a tombstone.
     */
    public function __construct(
        public readonly string $key,
        public readonly ?array $value,
    ) {
        $this->onQueue(self::QUEUE);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 15, 60];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureLeader];
    }

    public function handle(RaftClient $raft): void
    {
        try {
            $raft->set($this->key, $this->value);
        } catch (RaftUnavailableException $exception) {
            /*
             | A rejected write means this node lost leadership between the
             | dispatch and the attempt. Retrying is still the right move: the
             | EnsureLeader middleware drops the job on the next attempt, once
             | the cached role has caught up with the sidecar.
             */
            Log::warning($exception->isNotLeader()
                ? 'Publishing alert state was refused: this node is not the Raft leader.'
                : 'Publishing alert state to Raft failed.', [
                    'key' => $this->key,
                    'attempt' => $this->attempts(),
                    ...$exception->context(),
                ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Gave up publishing alert state to Raft; reconciliation will republish this key.', [
            'key' => $this->key,
            'version' => $this->value['version'] ?? null,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
