<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BackUp implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $alert;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct() {
//        $this->backup = $backup;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
        sleep(20);
//        \Artisan::call("backup:run", [
//            "--only-db"               => true,
//            "--disable-notifications" => true,
//        ]);
//        $this->backup->status = 2;
//        $this->backup->save();
    }

}
