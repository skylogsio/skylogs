<?php

namespace App\Jobs\Ha;

use App\Exceptions\Ha\RaftUnavailableException;
use App\Services\Ha\HaReconciler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs on every node, unlike the rest of the HA work: a follower reconciling is
 * how it catches up on deliveries it missed while it was down.
 */
class ReconcileHaStateJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'ha';

    public int $tries = 1;

    /**
     * A reconcile pass can outlast the minute between ticks on a large log;
     * overlapping passes would fight over the same slots.
     */
    public int $uniqueFor = 300;

    public function __construct()
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(HaReconciler $reconciler): void
    {
        try {
            Log::info('HA reconciliation finished.', $reconciler->reconcile());
        } catch (RaftUnavailableException $exception) {
            /*
             | Nothing to repair while the sidecar is down, and the next tick
             | costs nothing, so this is not worth failing the job over.
             */
            Log::warning('HA reconciliation skipped, the Raft sidecar is unreachable.', $exception->context());
        }
    }
}
