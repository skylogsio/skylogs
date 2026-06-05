<?php

use App\Enums\AlertRuleBehaviorRuleType;
use App\Models\AlertRule;
use App\Models\Notify;
use App\Services\AlertRuleBehaviorRuleService;
use App\Services\SendNotifyService;
use Tests\Support\Factories\AlertRuleFactory;

describe('SendNotifyService silent behavior rules', function () {
    it('sets notify status to silent when behavior silent triggers', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'endpointIds' => ['default-endpoint'],
            'userId' => 1,
            'silentUserIds' => [],
            'state' => AlertRule::RESOlVED,
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'dependsOnAlertRuleIds' => ['dep-1'],
                    'triggerState' => AlertRule::CRITICAL,
                ],
            ],
        ]);

        $notify = Mockery::mock(Notify::class)->makePartial();
        $notify->status = Notify::STATUS_CREATED;
        $notify->shouldReceive('save')->andReturnTrue();

        $notify->alert = [
            'instance' => 'mysql01',
        ];
        $notify->setRelation('alertRule', $alertRule);

        $behaviorRuleService = Mockery::mock(AlertRuleBehaviorRuleService::class);
        $behaviorRuleService->shouldReceive('resolveIsSilent')
            ->once()
            ->with($alertRule)
            ->andReturnTrue();
        $behaviorRuleService->shouldNotReceive('resolveEndpointIds');

        app()->instance(AlertRuleBehaviorRuleService::class, $behaviorRuleService);

        app(SendNotifyService::class)->SendMessage($notify);

        expect($notify->status)->toBe(Notify::STATUS_SILENT);
    });
});
