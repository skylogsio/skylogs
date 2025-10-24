<?php

namespace App\Jobs;

use App\Services\SkylogsInstanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LeaderPingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->onQueue('httpRequests');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        //        die();
        $serverPriority = SkylogsInstanceService::GetServerPriority();
        $leaderPriority = SkylogsInstanceService::GetLeaderPriority();

        if ($serverPriority <= $leaderPriority) {
            SkylogsInstanceService::SendPing();
        }

        SkylogsInstanceService::CheckLeaderPing();

    }
}
