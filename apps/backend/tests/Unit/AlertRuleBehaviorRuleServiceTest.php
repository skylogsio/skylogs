<?php

use App\Enums\AlertRuleBehaviorRuleType;
use App\Enums\AlertRuleType;
use App\Services\AlertRuleBehaviorRuleService;
use Tests\Support\Factories\AlertRuleFactory;

describe('AlertRuleBehaviorRuleService', function () {
    it('keeps default endpoints when no notification rules exist', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => ['default-endpoint'],
        ]);

        $endpointIds = app(AlertRuleBehaviorRuleService::class)->resolveEndpointIds($alertRule, [
            'alerts' => [
                ['labels' => ['db_name' => 'mysql01']],
            ],
        ]);

        expect($endpointIds)->toBe(['default-endpoint']);
    });

    it('adds notification rule endpoints when label filters match', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => ['default-endpoint'],
            'rules' => [
                [
                    'id' => 'rule-1',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => ['db_name' => 'mysql01'],
                    'endpointIds' => ['mysql-endpoint'],
                ],
            ],
        ]);

        $endpointIds = app(AlertRuleBehaviorRuleService::class)->resolveEndpointIds($alertRule, [
            'alerts' => [
                ['labels' => ['db_name' => 'mysql01']],
            ],
        ]);

        expect($endpointIds)->toContain('default-endpoint')
            ->and($endpointIds)->toContain('mysql-endpoint');
    });

    it('does not add notification rule endpoints when filters do not match', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => ['default-endpoint'],
            'rules' => [
                [
                    'id' => 'rule-1',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => ['db_name' => 'mysql01'],
                    'endpointIds' => ['mysql-endpoint'],
                ],
            ],
        ]);

        $endpointIds = app(AlertRuleBehaviorRuleService::class)->resolveEndpointIds($alertRule, [
            'alerts' => [
                ['labels' => ['db_name' => 'postgres01']],
            ],
        ]);

        expect($endpointIds)->toBe(['default-endpoint']);
    });

    it('matches api alerts using instance name', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::API,
            'endpointIds' => ['default-endpoint'],
            'rules' => [
                [
                    'id' => 'rule-1',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => ['instance' => 'web-01'],
                    'endpointIds' => ['web-endpoint'],
                ],
            ],
        ]);

        $endpointIds = app(AlertRuleBehaviorRuleService::class)->resolveEndpointIds($alertRule, [
            'instance' => 'web-01',
        ]);

        expect($endpointIds)->toContain('web-endpoint');
    });

    it('supports regex style filter patterns', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::GRAFANA,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'rule-1',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => ['db_name' => 'mysql*'],
                    'endpointIds' => ['mysql-endpoint'],
                ],
            ],
        ]);

        $endpointIds = app(AlertRuleBehaviorRuleService::class)->resolveEndpointIds($alertRule, [
            'alerts' => [
                ['labels' => ['db_name' => 'mysql01']],
            ],
        ]);

        expect($endpointIds)->toBe(['mysql-endpoint']);
    });

    it('normalizes list style filters', function () {
        $service = app(AlertRuleBehaviorRuleService::class);

        expect($service->normalizeFilters([
            ['key' => 'db_name', 'value' => 'mysql01'],
        ]))->toBe(['db_name' => 'mysql01']);
    });
});
