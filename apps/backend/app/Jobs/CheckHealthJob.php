<?php

namespace App\Jobs;

use App\Services\HealthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckHealthJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $alert;

    public function __construct($alert)
    {
        $this->onQueue('httpRequests');
        $this->alert = $alert;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //        echo "TEST";
        app(HealthService::class)->check($this->alert);

    }

    public function uniqueId()
    {
        return $this->alert->_id;
    }
}
