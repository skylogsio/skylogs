<?php

namespace App\Jobs;

use App\Queue\Middleware\EnsureLeader;
use App\Services\ClusterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCluster implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureLeader];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        app(ClusterService::class)->syncData();
    }
}
