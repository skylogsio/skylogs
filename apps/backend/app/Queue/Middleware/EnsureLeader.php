<?php

namespace App\Queue\Middleware;

use App\Services\Ha\HaLeaderService;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Drops leader-only jobs on a follower.
 *
 * The scheduler already refuses to queue this work on a follower; this catches
 * the jobs that were queued moments before a demotion and would otherwise be
 * evaluated — and notified on — by two nodes at once.
 */
class EnsureLeader
{
    /**
     * @param  Closure(object): void  $next
     */
    public function handle(object $job, Closure $next): void
    {
        $leader = app(HaLeaderService::class);

        if ($leader->shouldRunLeaderWork()) {
            $next($job);

            return;
        }

        Log::debug('Dropping leader-only job on a follower node.', [
            'job' => $job::class,
            'nodeId' => $leader->nodeId(),
        ]);

        if (method_exists($job, 'delete')) {
            $job->delete();
        }
    }
}
