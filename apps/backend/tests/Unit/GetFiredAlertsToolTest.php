<?php

use App\Mcp\Servers\SkylogsServer;
use App\Mcp\Tools\GetFiredAlertsTool;
use App\Services\AlertRuleService;

use function Pest\Laravel\mock;

it('returns fired alerts for an alert rule', function () {
    $alertRuleId = '507f1f77bcf86cd799439011';
    $firedAlerts = [
        ['id' => 'alert-1', 'state' => 2, 'summary' => 'CPU high'],
    ];

    mock(AlertRuleService::class)
        ->shouldReceive('firedAlerts')
        ->once()
        ->with($alertRuleId)
        ->andReturn($firedAlerts);

    SkylogsServer::tool(GetFiredAlertsTool::class, [
        'alertRuleId' => $alertRuleId,
    ])
        ->assertOk()
        ->assertSee('"alertRuleId":"'.$alertRuleId.'"')
        ->assertSee('"summary":"CPU high"');
});

it('returns all critical alert rules when alert rule id is omitted', function () {
    $criticalRules = [
        [
            'alertRuleId' => '507f1f77bcf86cd799439011',
            'name' => 'High CPU',
            'type' => 'prometheus',
            'state' => 'critical',
            'fireCount' => 1,
            'tags' => ['production'],
            'firedAlerts' => [
                ['summary' => 'CPU above 90%'],
            ],
        ],
    ];

    mock(AlertRuleService::class)
        ->shouldReceive('firedAlertsForCriticalRules')
        ->once()
        ->andReturn($criticalRules);

    SkylogsServer::tool(GetFiredAlertsTool::class, [])
        ->assertOk()
        ->assertSee('"firedAlertRules"')
        ->assertSee('"name":"High CPU"')
        ->assertSee('"summary":"CPU above 90%"');
});

it('validates the alert rule id', function () {
    SkylogsServer::tool(GetFiredAlertsTool::class, [
        'alertRuleId' => 'invalid-id',
    ])->assertHasErrors();
});

it('lists the get-fired-alerts tool on the server', function () {
    $tool = app(GetFiredAlertsTool::class);

    expect($tool->name())->toBe('get-fired-alerts');
});
