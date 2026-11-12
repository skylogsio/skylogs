<?php

namespace App\Jobs\Ha;

use App\Exceptions\Ha\LeaderUnavailableException;
use App\Services\Ha\HaHistoryPuller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched on every node; the puller decides that only followers do work.
 */
class SyncHaHistoryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'ha';

    public int $tries = 1;

    /**
     * Catch-up can page for a while; overlapping passes would race on cursors.
     */
    public int $uniqueFor = 300;

    public function __construct()
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(HaHistoryPuller $puller): void
    {
        try {
            $result = $puller->pull();
        } catch (LeaderUnavailableException $exception) {
            Log::warning('HA history sync skipped, the leader is unreachable.', [
                'reason' => $exception->getMessage(),
                ...$exception->context(),
            ]);

            return;
        }

        if ($result['status'] === 'pulled') {
            $written = collect($result['collections'] ?? [])->sum('written');

            if ($written > 0) {
                Log::info('HA history sync applied documents.', $result);
            }
        }
    }
}
