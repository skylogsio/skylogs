<?php

namespace App\Jobs;

use App\Queue\Middleware\EnsureLeader;
use App\Services\Health\HealthCheckRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunHealthChecksJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 10;

    public int $uniqueFor = 10;

    public function __construct()
    {
        $this->onQueue('httpRequests');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureLeader];
    }

    public function uniqueId(): string
    {
        return 'run-health-checks';
    }

    public function handle(HealthCheckRunner $runner): void
    {
        $runner->run();
    }
}
