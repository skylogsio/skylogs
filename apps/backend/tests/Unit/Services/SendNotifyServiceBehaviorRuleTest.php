<?php

use App\Models\AlertRule;
use App\Models\Notify;
use App\Models\User;
use App\Services\AlertRuleBehaviorRuleService;
use App\Services\SendNotifyService;
use App\Services\UserService;
use Tests\Support\Factories\AlertRuleFactory;

describe('SendNotifyService notification behavior rules', function () {
    it('resolves endpoint ids from alert payload via behavior rule service', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'endpointIds' => ['default-endpoint'],
            'userId' => 1,
            'silentUserIds' => [],
            'state' => AlertRule::RESOlVED,
        ]);

        $notify = Mockery::mock(Notify::class)->makePartial();
        $notify->shouldReceive('save')->andReturnTrue();
        $notify->alert = [
            'instance' => 'mysql01',
            'labels' => ['db_name' => 'mysql01'],
        ];
        $notify->setRelation('alertRule', $alertRule);

        $behaviorRuleService = Mockery::mock(AlertRuleBehaviorRuleService::class);
        $behaviorRuleService->shouldReceive('resolveEndpointIds')
            ->once()
            ->with(
                Mockery::on(fn (AlertRule $rule) => $rule->endpointIds === ['default-endpoint']),
                $notify->alert,
            )
            ->andReturn(['default-endpoint', 'mysql-endpoint']);

        app()->instance(AlertRuleBehaviorRuleService::class, $behaviorRuleService);

        $admin = new User;
        $admin->id = 999;

        $userService = Mockery::mock(UserService::class);
        $userService->shouldReceive('admin')->andReturn($admin);
        app()->instance(UserService::class, $userService);

        SendNotifyService::SendMessage($notify);

        expect($notify->endpointIds)->toBe(['default-endpoint', 'mysql-endpoint']);
    });
});
