<?php

namespace App\Console\Commands\Ha;

use App\Exceptions\Ha\RaftUnavailableException;
use App\Services\Ha\HaReconciler;
use Illuminate\Console\Command;

class ReconcileHaState extends Command
{
    protected $signature = 'ha:reconcile';

    protected $description = 'Bring this node back into agreement with the replicated Raft alert state';

    public function handle(HaReconciler $reconciler): int
    {
        if (! config('ha.enabled')) {
            $this->components->info('High availability is disabled; nothing to reconcile.');

            return self::SUCCESS;
        }

        try {
            $summary = $reconciler->reconcile();
        } catch (RaftUnavailableException $exception) {
            $this->components->warn('The Raft sidecar is unreachable: '.$exception->getMessage());

            /*
             | Deliberately not a failure. This command runs from the container
             | entrypoint, where a sidecar that has not finished electing must
             | not stop the application from booting.
             */
            return self::SUCCESS;
        }

        foreach ($summary as $label => $value) {
            $this->components->twoColumnDetail($label, (string) $value);
        }

        return self::SUCCESS;
    }
}
