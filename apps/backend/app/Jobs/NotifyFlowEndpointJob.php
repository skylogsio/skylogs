<?php

namespace App\Jobs;

use App\Models\Notify;
use App\Services\SendNotifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// use Log;

class NotifyFlowEndpointJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $notify;

    public $endpointId;

    public $currentStepIndex;

    public function __construct(Notify $notify, $endpointId, int $currentStepIndex = 0)
    {
        $this->notify = $notify;
        $this->endpointId = $endpointId;
        $this->currentStepIndex = $currentStepIndex;
        $this->onQueue('sendNotifies');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        app(SendNotifyService::class)->processStep($this->notify, $this->endpointId, $this->currentStepIndex);
    }

    public function fail($exception = null) {}
}
