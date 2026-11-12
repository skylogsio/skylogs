<?php

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Services\AlertRuleService;
use App\Services\TeamService;
use Tests\Support\Factories\AlertRuleFactory;

describe('AlertRuleService selectable alerts for silent behavior rules', function () {
    it('includes only alert types that support resolved or critical status', function () {
        $service = new AlertRuleService(Mockery::mock(TeamService::class));

        $supportedTypes = array_map(
            fn (AlertRuleType $type) => $type->value,
            $service->silentDependencySupportedTypes(),
        );

        expect($supportedTypes)->toContain(
            AlertRuleType::API->value,
            AlertRuleType::PROMETHEUS->value,
            AlertRuleType::HEALTH->value,
            AlertRuleType::ELASTIC->value,
        )->not->toContain(
            AlertRuleType::NOTIFICATION->value,
            AlertRuleType::SPLUNK->value,
        );
    });

    it('formats selectable alert rules for the API', function () {
        $service = new AlertRuleService(Mockery::mock(TeamService::class));

        $alertRule = AlertRuleFactory::unsaved([
            '_id' => '507f1f77bcf86cd799439011',
            'name' => 'Disk usage',
            'type' => AlertRuleType::API,
            'state' => AlertRule::CRITICAL,
        ]);

        expect($service->formatSelectableAlertRulesForApi([$alertRule]))->toBe([
            [
                'id' => '507f1f77bcf86cd799439011',
                'name' => 'Disk usage',
                'type' => AlertRuleType::API->value,
                'state' => AlertRule::CRITICAL,
            ],
        ]);
    });
});
