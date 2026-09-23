<?php

namespace App\Console\Commands;

use App\Services\AlertRuleService;
use Illuminate\Console\Command;

class BackfillDatasourceHealthRules extends Command
{
    protected $signature = 'health:backfill-datasource-rules';

    protected $description = 'Create a datasource health alert rule for each datasource that does not already have one';

    public function handle(AlertRuleService $alertRules): int
    {
        $result = $alertRules->backfillHealthDataSources();

        $this->components->twoColumnDetail('created', (string) $result['created']);
        $this->components->twoColumnDetail('skipped', (string) $result['skipped']);

        if ($result['unresolved'] > 0) {
            $this->components->error('Could not resolve a user for '.$result['unresolved'].' datasource(s).');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
