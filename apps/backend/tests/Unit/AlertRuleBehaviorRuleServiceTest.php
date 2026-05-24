<?php

use App\Enums\AlertRuleBehaviorRuleType;
use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Services\AlertRuleBehaviorRuleService;
use Tests\Support\Factories\AlertRuleFactory;

describe('AlertRuleBehaviorRuleService', function () {
    beforeEach(function () {
        $this->service = app(AlertRuleBehaviorRuleService::class);
    });

    it('keeps default endpoints when no notification rules exist', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => ['default-endpoint'],
        ]);

        $endpointIds = $this->service->resolveEndpointIds($alertRule, [
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

        $endpointIds = $this->service->resolveEndpointIds($alertRule, [
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

        $endpointIds = $this->service->resolveEndpointIds($alertRule, [
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

        $endpointIds = $this->service->resolveEndpointIds($alertRule, [
            'instance' => 'web-01',
        ]);

        expect($endpointIds)->toContain('web-endpoint');
    });

    it('supports wildcard filter patterns', function () {
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

        $endpointIds = $this->service->resolveEndpointIds($alertRule, [
            'alerts' => [
                ['labels' => ['db_name' => 'mysql01']],
            ],
        ]);

        expect($endpointIds)->toBe(['mysql-endpoint']);
    });

    it('normalizes list style filters', function () {
        expect($this->service->normalizeFilters([
            ['key' => 'db_name', 'value' => 'mysql01'],
        ]))->toBe(['db_name' => 'mysql01']);
    });

    it('normalizes associative filters', function () {
        expect($this->service->normalizeFilters([
            'db_name' => 'mysql01',
            'env' => 'prod',
        ]))->toBe([
            'db_name' => 'mysql01',
            'env' => 'prod',
        ]);
    });

    it('requires all filters to match', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'rule-1',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => [
                        'db_name' => 'mysql01',
                        'env' => 'prod',
                    ],
                    'endpointIds' => ['matched-endpoint'],
                ],
            ],
        ]);

        $matching = $this->service->resolveEndpointIds($alertRule, [
            'alerts' => [
                ['labels' => ['db_name' => 'mysql01', 'env' => 'prod']],
            ],
        ]);

        $notMatching = $this->service->resolveEndpointIds($alertRule, [
            'alerts' => [
                ['labels' => ['db_name' => 'mysql01', 'env' => 'staging']],
            ],
        ]);

        expect($matching)->toBe(['matched-endpoint'])
            ->and($notMatching)->toBe([]);
    });

    it('matches annotation values for label-based alert types', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::GRAFANA,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'rule-1',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => ['summary' => 'disk full'],
                    'endpointIds' => ['disk-endpoint'],
                ],
            ],
        ]);

        $endpointIds = $this->service->resolveEndpointIds($alertRule, [
            'alerts' => [
                [
                    'labels' => ['alertname' => 'Disk'],
                    'annotations' => ['summary' => 'disk full'],
                ],
            ],
        ]);

        expect($endpointIds)->toBe(['disk-endpoint']);
    });

    it('matches when any alert in a batch matches', function () {
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

        $endpointIds = $this->service->resolveEndpointIds($alertRule, [
            'alerts' => [
                ['labels' => ['db_name' => 'postgres01']],
                ['labels' => ['db_name' => 'mysql01']],
            ],
        ]);

        expect($endpointIds)->toContain('mysql-endpoint');
    });

    it('merges endpoints from multiple matching notification rules', function () {
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
                [
                    'id' => 'rule-2',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => ['env' => 'prod'],
                    'endpointIds' => ['prod-endpoint'],
                ],
            ],
        ]);

        $endpointIds = $this->service->resolveEndpointIds($alertRule, [
            'alerts' => [
                ['labels' => ['db_name' => 'mysql01', 'env' => 'prod']],
            ],
        ]);

        expect($endpointIds)->toContain('default-endpoint')
            ->and($endpointIds)->toContain('mysql-endpoint')
            ->and($endpointIds)->toContain('prod-endpoint');
    });

    it('ignores notification rules with empty filters', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::API,
            'endpointIds' => ['default-endpoint'],
            'rules' => [
                [
                    'id' => 'rule-1',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => [],
                    'endpointIds' => ['skipped-endpoint'],
                ],
            ],
        ]);

        $endpointIds = $this->service->resolveEndpointIds($alertRule, [
            'instance' => 'web-01',
        ]);

        expect($endpointIds)->toBe(['default-endpoint']);
    });

    it('formats rules for api response', function () {
        $formatted = $this->service->formatRulesForApi([
            [
                'id' => 'rule-1',
                'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                'filters' => ['db_name' => 'mysql01'],
                'endpointIds' => ['endpoint-a'],
            ],
        ]);

        expect($formatted)->toBe([
            [
                'id' => 'rule-1',
                'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                'filters' => [
                    ['key' => 'db_name', 'value' => 'mysql01'],
                ],
                'endpointIds' => ['endpoint-a'],
            ],
        ]);
    });

    it('creates a notification rule on the alert rule', function () {
        $alertRule = mockAlertRuleForPersistence(['rules' => []]);

        $rule = $this->service->createNotificationRule($alertRule, [
            'filters' => [['key' => 'db_name', 'value' => 'mysql01']],
            'endpointIds' => ['endpoint-a', 'endpoint-a'],
        ]);

        expect($rule['type'])->toBe(AlertRuleBehaviorRuleType::NOTIFICATION->value)
            ->and($rule['filters'])->toBe(['db_name' => 'mysql01'])
            ->and($rule['endpointIds'])->toBe(['endpoint-a'])
            ->and($alertRule->rules)->toHaveCount(1)
            ->and($alertRule->rules[0]['id'])->not->toBeEmpty();
    });

    it('updates an existing notification rule', function () {
        $alertRule = mockAlertRuleForPersistence([
            'rules' => [
                [
                    'id' => 'existing-rule-id',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => ['db_name' => 'mysql01'],
                    'endpointIds' => ['old-endpoint'],
                ],
            ],
        ]);

        $updated = $this->service->updateNotificationRule($alertRule, 'existing-rule-id', [
            'filters' => [['key' => 'db_name', 'value' => 'mysql02']],
            'endpointIds' => ['new-endpoint'],
        ]);

        expect($updated)->not->toBeNull()
            ->and($updated['filters'])->toBe(['db_name' => 'mysql02'])
            ->and($updated['endpointIds'])->toBe(['new-endpoint'])
            ->and($alertRule->rules[0]['endpointIds'])->toBe(['new-endpoint']);
    });

    it('returns null when updating a missing rule', function () {
        $alertRule = mockAlertRuleForPersistence(['rules' => []]);

        expect($this->service->updateNotificationRule($alertRule, 'missing', [
            'endpointIds' => ['new-endpoint'],
        ]))->toBeNull();
    });

    it('deletes a rule by id', function () {
        $alertRule = mockAlertRuleForPersistence([
            'rules' => [
                [
                    'id' => 'rule-to-delete',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => ['db_name' => 'mysql01'],
                    'endpointIds' => ['endpoint-a'],
                ],
            ],
        ]);

        expect($this->service->deleteRule($alertRule, 'rule-to-delete'))->toBeTrue()
            ->and($alertRule->rules)->toBe([]);
    });

    it('returns false when deleting a missing rule', function () {
        $alertRule = mockAlertRuleForPersistence(['rules' => []]);

        expect($this->service->deleteRule($alertRule, 'missing'))->toBeFalse();
    });

    it('filters only notification rules from mixed rule types', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'filters' => ['db_name' => 'mysql01'],
                    'endpointIds' => ['silent-endpoint'],
                ],
                [
                    'id' => 'notify-1',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => ['db_name' => 'mysql01'],
                    'endpointIds' => ['notify-endpoint'],
                ],
            ],
        ]);

        expect($this->service->notificationRules($alertRule))->toHaveCount(1)
            ->and($this->service->notificationRules($alertRule)->first()['id'])->toBe('notify-1');
    });
});

/**
 * @param  array<string, mixed>  $attributes
 */
function mockAlertRuleForPersistence(array $attributes): AlertRule
{
    $alertRule = AlertRuleFactory::unsaved($attributes);
    $mock = Mockery::mock($alertRule)->makePartial();
    $mock->shouldReceive('save')->andReturnTrue();

    return $mock;
}
