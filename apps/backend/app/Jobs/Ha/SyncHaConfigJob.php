<?php

namespace App\Jobs\Ha;

use App\Exceptions\Ha\LeaderUnavailableException;
use App\Services\Ha\HaConfigPuller;
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
class SyncHaConfigJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'ha';

    public int $tries = 1;

    /**
     * A first sync writes every replicated collection and can outlast the gap
     * between ticks; overlapping passes would write the same documents twice.
     */
    public int $uniqueFor = 300;

    public function __construct()
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(HaConfigPuller $puller): void
    {
        try {
            $result = $puller->pull();
        } catch (LeaderUnavailableException $exception) {
            /*
             | An election in progress or a leader mid restart both land here.
             | The follower keeps serving the configuration it already has and
             | the next tick retries, so this is not worth failing over.
             */
            Log::warning('HA config sync skipped, the leader is unreachable.', $exception->context());

            return;
        }

        if ($result['status'] === 'applied') {
            Log::info('HA config sync applied a new snapshot.', $result);
        }
    }
}
