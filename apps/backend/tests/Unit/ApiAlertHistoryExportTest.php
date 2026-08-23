<?php

use App\Exports\ApiAlertHistoryExport;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
use Carbon\Carbon;

it('maps history rows with status labels', function () {
    $alert = new AlertRule(['name' => 'API Export Alert']);
    $export = new ApiAlertHistoryExport(
        $alert,
        Carbon::create(2026, 1, 1),
        Carbon::create(2026, 1, 2),
    );

    $fire = new ApiAlertHistory([
        'alertRuleName' => 'API Export Alert',
        'instance' => 'web-1',
        'description' => 'High CPU',
        'summary' => 'cpu > 90',
        'state' => ApiAlertHistory::FIRE,
    ]);

    $resolved = new ApiAlertHistory([
        'alertRuleName' => 'API Export Alert',
        'instance' => 'web-1',
        'description' => 'Recovered',
        'summary' => 'cpu normal',
        'state' => ApiAlertHistory::RESOLVED,
    ]);

    expect($export->map($fire))->toContain('critical')
        ->and($export->map($fire))->toContain('High CPU')
        ->and($export->map($resolved))->toContain('resolved')
        ->and($export->map($resolved))->toContain('Recovered');
});
