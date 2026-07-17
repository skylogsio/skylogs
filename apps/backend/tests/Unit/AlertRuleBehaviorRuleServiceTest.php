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

    it('does not match api notification rules when filter key is not instance', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::API,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'rule-1',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => ['dtest' => 'test'],
                    'endpointIds' => ['wrong-endpoint'],
                ],
                [
                    'id' => 'rule-2',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => ['instance' => 'test'],
                    'endpointIds' => ['matched-endpoint'],
                ],
            ],
        ]);

        $endpointIds = $this->service->resolveEndpointIds($alertRule, [
            'instance' => 'test',
        ]);

        expect($endpointIds)->toBe(['matched-endpoint'])
            ->not->toContain('wrong-endpoint');
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

    it('requires every filter entry to match including duplicate keys', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'rule-1',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => [
                        ['key' => 'instance', 'value' => 'web-*'],
                        ['key' => 'instance', 'value' => '*-prod'],
                    ],
                    'endpointIds' => ['matched-endpoint'],
                ],
            ],
        ]);

        $matching = $this->service->resolveEndpointIds($alertRule, [
            'alerts' => [
                ['labels' => ['instance' => 'web-server-prod']],
            ],
        ]);

        $notMatching = $this->service->resolveEndpointIds($alertRule, [
            'alerts' => [
                ['labels' => ['instance' => 'web-server-dev']],
            ],
        ]);

        expect($matching)->toBe(['matched-endpoint'])
            ->and($notMatching)->toBe([]);
    });

    it('does not add notification rule endpoints when only one filter in a list matches', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => ['default-endpoint'],
            'rules' => [
                [
                    'id' => 'rule-1',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => [
                        ['key' => 'db_name', 'value' => 'mysql01'],
                        ['key' => 'env', 'value' => 'prod'],
                    ],
                    'endpointIds' => ['matched-endpoint'],
                ],
            ],
        ]);

        $endpointIds = $this->service->resolveEndpointIds($alertRule, [
            'alerts' => [
                ['labels' => ['db_name' => 'mysql01', 'env' => 'staging']],
            ],
        ]);

        expect($endpointIds)->toBe(['default-endpoint'])
            ->not->toContain('matched-endpoint');
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
                'name' => 'MySQL endpoints',
                'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                'filters' => ['db_name' => 'mysql01'],
                'endpointIds' => ['endpoint-a'],
            ],
        ]);

        expect($formatted)->toBe([
            [
                'id' => 'rule-1',
                'name' => 'MySQL endpoints',
                'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                'filters' => [
                    ['key' => 'db_name', 'value' => 'mysql01'],
                ],
                'endpointIds' => ['endpoint-a'],
                'endpoints' => [
                    ['id' => 'endpoint-a', 'name' => ''],
                ],
            ],
        ]);
    });

    it('includes resolved endpoint names in api response', function () {
        $service = Mockery::mock(AlertRuleBehaviorRuleService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('endpointNamesByIds')
            ->with(['endpoint-a'])
            ->andReturn(['endpoint-a' => 'Ops email']);

        $formatted = $service->formatRulesForApi([
            [
                'id' => 'rule-1',
                'name' => 'MySQL endpoints',
                'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                'filters' => ['db_name' => 'mysql01'],
                'endpointIds' => ['endpoint-a'],
            ],
        ]);

        expect($formatted[0]['endpoints'])->toBe([
            ['id' => 'endpoint-a', 'name' => 'Ops email'],
        ]);
    });

    it('defaults missing rule names to empty string in api response', function () {
        $formatted = $this->service->formatRulesForApi([
            [
                'id' => 'rule-1',
                'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                'filters' => ['db_name' => 'mysql01'],
                'endpointIds' => ['endpoint-a'],
            ],
        ]);

        expect($formatted[0]['name'])->toBe('');
    });

    it('creates a notification rule on the alert rule', function () {
        $alertRule = mockAlertRuleForPersistence(['rules' => []]);

        $rule = $this->service->createNotificationRule($alertRule, [
            'name' => 'MySQL endpoints',
            'filters' => [['key' => 'db_name', 'value' => 'mysql01']],
            'endpointIds' => ['endpoint-a', 'endpoint-a'],
        ]);

        expect($rule['name'])->toBe('MySQL endpoints')
            ->and($rule['type'])->toBe(AlertRuleBehaviorRuleType::NOTIFICATION->value)
            ->and($rule['filters'])->toBe([
                ['key' => 'db_name', 'value' => 'mysql01'],
            ])
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
            'name' => 'Updated rule name',
            'filters' => [['key' => 'db_name', 'value' => 'mysql02']],
            'endpointIds' => ['new-endpoint'],
        ]);

        expect($updated)->not->toBeNull()
            ->and($updated['name'])->toBe('Updated rule name')
            ->and($updated['filters'])->toBe([
                ['key' => 'db_name', 'value' => 'mysql02'],
            ])
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

    it('resolves endpoint templates from template rules', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'rules' => [
                [
                    'id' => 'template-1',
                    'type' => AlertRuleBehaviorRuleType::TEMPLATE->value,
                    'endpointIds' => ['endpoint-a', 'endpoint-b'],
                    'template' => 'Alert: {{name}}',
                ],
                [
                    'id' => 'template-2',
                    'type' => AlertRuleBehaviorRuleType::TEMPLATE->value,
                    'endpointIds' => ['endpoint-c'],
                    'template' => 'Other: {{name}}',
                ],
            ],
        ]);

        expect($this->service->resolveEndpointTemplates($alertRule))->toBe([
            'endpoint-a' => 'Alert: {{name}}',
            'endpoint-b' => 'Alert: {{name}}',
            'endpoint-c' => 'Other: {{name}}',
        ]);
    });

    it('creates a template rule on the alert rule', function () {
        $alertRule = mockAlertRuleForPersistence(['rules' => []]);

        $rule = $this->service->createTemplateRule($alertRule, [
            'name' => 'Custom template',
            'endpointIds' => ['endpoint-a', 'endpoint-a'],
            'template' => 'Hello {{name}}',
        ]);

        expect($rule['name'])->toBe('Custom template')
            ->and($rule['type'])->toBe(AlertRuleBehaviorRuleType::TEMPLATE->value)
            ->and($rule['template'])->toBe('Hello {{name}}')
            ->and($rule['endpointIds'])->toBe(['endpoint-a'])
            ->and($alertRule->rules)->toHaveCount(1);
    });

    it('updates an existing template rule', function () {
        $alertRule = mockAlertRuleForPersistence([
            'rules' => [
                [
                    'id' => 'template-rule-id',
                    'type' => AlertRuleBehaviorRuleType::TEMPLATE->value,
                    'endpointIds' => ['old-endpoint'],
                    'template' => 'Old text',
                ],
            ],
        ]);

        $updated = $this->service->updateTemplateRule($alertRule, 'template-rule-id', [
            'endpointIds' => ['new-endpoint'],
            'template' => 'New text',
        ]);

        expect($updated)->not->toBeNull()
            ->and($updated['template'])->toBe('New text')
            ->and($updated['endpointIds'])->toBe(['new-endpoint']);
    });

    it('formats template rules for api response', function () {
        $formatted = $this->service->formatRulesForApi([
            [
                'id' => 'template-1',
                'name' => 'Custom template',
                'type' => AlertRuleBehaviorRuleType::TEMPLATE->value,
                'endpointIds' => ['endpoint-a'],
                'template' => 'Hi {{name}}',
            ],
        ]);

        expect($formatted[0])->toMatchArray([
            'id' => 'template-1',
            'name' => 'Custom template',
            'type' => AlertRuleBehaviorRuleType::TEMPLATE->value,
            'endpointIds' => ['endpoint-a'],
            'endpoints' => [
                ['id' => 'endpoint-a', 'name' => ''],
            ],
            'template' => 'Hi {{name}}',
        ])->and($formatted[0])->not->toHaveKey('filters');
    });

    it('formats silent rules for api response', function () {
        $service = Mockery::mock(AlertRuleBehaviorRuleService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('alertRuleNamesByIds')
            ->with(['dep-1', 'dep-2'])
            ->andReturn([]);

        $formatted = $service->formatRulesForApi([
            [
                'id' => 'silent-1',
                'name' => 'Silence when deps resolved',
                'type' => AlertRuleBehaviorRuleType::SILENT->value,
                'dependsOnAlertRuleIds' => ['dep-1', 'dep-2'],
                'triggerState' => AlertRule::RESOlVED,
            ],
        ]);

        expect($formatted[0])->toMatchArray([
            'id' => 'silent-1',
            'name' => 'Silence when deps resolved',
            'type' => AlertRuleBehaviorRuleType::SILENT->value,
            'triggerState' => AlertRule::RESOlVED,
            'dependsOnAlertRuleIds' => ['dep-1', 'dep-2'],
            'dependsOnAlertRules' => [
                ['id' => 'dep-1', 'name' => ''],
                ['id' => 'dep-2', 'name' => ''],
            ],
        ]);
    });

    it('includes resolved alert rule names in api response for silent rules', function () {
        $service = Mockery::mock(AlertRuleBehaviorRuleService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('alertRuleNamesByIds')
            ->with(['dep-1'])
            ->andReturn(['dep-1' => 'MySQL alert']);

        $formatted = $service->formatRulesForApi([
            [
                'id' => 'silent-1',
                'name' => 'Silence when deps resolved',
                'type' => AlertRuleBehaviorRuleType::SILENT->value,
                'dependsOnAlertRuleIds' => ['dep-1'],
                'triggerState' => AlertRule::CRITICAL,
            ],
        ]);

        expect($formatted[0]['dependsOnAlertRules'])->toBe([
            ['id' => 'dep-1', 'name' => 'MySQL alert'],
        ])->and($formatted[0])->not->toHaveKeys(['template', 'endpointIds', 'endpoints'])
            ->and($formatted[0]['filters'])->toBe([]);
    });

    it('filters only notification rules from mixed rule types', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'filters' => ['db_name' => 'mysql01'],
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

    it('filters only silent rules from mixed rule types', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'dependsOnAlertRuleIds' => ['dep-1'],
                    'triggerState' => AlertRule::RESOlVED,
                ],
                [
                    'id' => 'notify-1',
                    'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    'filters' => ['db_name' => 'mysql01'],
                    'endpointIds' => ['notify-endpoint'],
                ],
            ],
        ]);

        expect($this->service->silentRules($alertRule))->toHaveCount(1)
            ->and($this->service->silentRules($alertRule)->first()['id'])->toBe('silent-1');
    });

    it('resolves silent when a dependent alert rule is resolved', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'dependsOnAlertRuleIds' => ['dep-1'],
                    'triggerState' => AlertRule::RESOlVED,
                ],
            ],
        ]);

        $dependentAlert = Mockery::mock(AlertRule::class);
        $dependentAlert->shouldReceive('getStatus')->andReturn([AlertRule::RESOlVED, 0]);

        $service = Mockery::mock(AlertRuleBehaviorRuleService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('findDependentAlertRules')
            ->with(['dep-1'])
            ->andReturn(collect([$dependentAlert]));

        expect($service->resolveIsSilent($alertRule))->toBeTrue();
    });

    it('resolves silent when a dependent alert rule is critical', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'dependsOnAlertRuleIds' => ['dep-1'],
                    'triggerState' => AlertRule::CRITICAL,
                ],
            ],
        ]);

        $dependentAlert = Mockery::mock(AlertRule::class);
        $dependentAlert->shouldReceive('getStatus')->andReturn([AlertRule::CRITICAL, 0]);

        $service = Mockery::mock(AlertRuleBehaviorRuleService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('findDependentAlertRules')
            ->with(['dep-1'])
            ->andReturn(collect([$dependentAlert]));

        expect($service->resolveIsSilent($alertRule))->toBeTrue();
    });

    it('returns false when no dependent alert rule matches trigger state', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'dependsOnAlertRuleIds' => ['dep-1'],
                    'triggerState' => AlertRule::RESOlVED,
                ],
            ],
        ]);

        $dependentAlert = Mockery::mock(AlertRule::class);
        $dependentAlert->shouldReceive('getStatus')->andReturn([AlertRule::CRITICAL, 0]);

        $service = Mockery::mock(AlertRuleBehaviorRuleService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('findDependentAlertRules')
            ->with(['dep-1'])
            ->andReturn(collect([$dependentAlert]));

        expect($service->resolveIsSilent($alertRule))->toBeFalse();
    });

    it('returns false when there are no silent rules', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [],
        ]);

        $service = Mockery::mock(AlertRuleBehaviorRuleService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldNotReceive('findDependentAlertRules');

        expect($service->resolveIsSilent($alertRule))->toBeFalse();
    });

    it('formats silent rules with filters and time bounds for api response', function () {
        $formatted = $this->service->formatRulesForApi([
            [
                'id' => 'silent-1',
                'name' => 'Silence api instance',
                'type' => AlertRuleBehaviorRuleType::SILENT->value,
                'filters' => [['key' => 'instance', 'value' => 'api*']],
                'startsAt' => 1_720_000_000,
                'endsAt' => 1_721_289_600,
            ],
        ]);

        expect($formatted[0])->toMatchArray([
            'id' => 'silent-1',
            'name' => 'Silence api instance',
            'type' => AlertRuleBehaviorRuleType::SILENT->value,
            'filters' => [
                ['key' => 'instance', 'value' => 'api*'],
            ],
            'startsAt' => 1_720_000_000,
            'endsAt' => 1_721_289_600,
        ]);
    });

    it('resolves silent for time-only rule within active window', function () {
        $now = 1_720_500_000;
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'startsAt' => 1_720_000_000,
                    'endsAt' => 1_721_000_000,
                ],
            ],
        ]);

        expect($this->service->resolveIsSilent($alertRule, null, $now))->toBeTrue();
    });

    it('does not resolve silent for time-only rule outside active window', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'startsAt' => 1_720_000_000,
                    'endsAt' => 1_721_000_000,
                ],
            ],
        ]);

        expect($this->service->silentRuleMatches(
            $alertRule,
            $alertRule->rules[0],
            null,
            1_722_000_000,
        ))->toBeFalse();
    });

    it('resolves silent when prometheus label filters match notify payload', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'filters' => [['key' => 'job', 'value' => 'prometheus*']],
                ],
            ],
        ]);

        $payload = [
            'alerts' => [
                ['labels' => ['job' => 'prometheus-main', 'instance' => 'host1']],
            ],
        ];

        expect($this->service->resolveIsSilent($alertRule, $payload))->toBeTrue();
    });

    it('does not resolve silent when prometheus label filters do not match', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'filters' => [['key' => 'job', 'value' => 'prometheus*']],
                ],
            ],
        ]);

        $payload = [
            'alerts' => [
                ['labels' => ['job' => 'grafana', 'instance' => 'host1']],
            ],
        ];

        expect($this->service->resolveIsSilent($alertRule, $payload))->toBeFalse();
    });

    it('does not resolve filter-only silent rule without notify payload', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'filters' => [['key' => 'job', 'value' => 'prometheus*']],
                ],
            ],
        ]);

        expect($this->service->resolveIsSilent($alertRule))->toBeFalse();
    });

    it('resolves silent for api instance filter when payload matches', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::API,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'filters' => [['key' => 'instance', 'value' => 'api*']],
                ],
            ],
        ]);

        expect($this->service->resolveIsSilent($alertRule, ['instance' => 'api-server']))->toBeTrue();
    });

    it('requires all configured silent dimensions to match', function () {
        $now = 1_720_500_000;
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'dependsOnAlertRuleIds' => ['dep-1'],
                    'triggerState' => AlertRule::CRITICAL,
                    'filters' => [['key' => 'instance', 'value' => 'api*']],
                    'startsAt' => 1_720_000_000,
                    'endsAt' => 1_721_000_000,
                ],
            ],
        ]);

        $dependentAlert = Mockery::mock(AlertRule::class);
        $dependentAlert->shouldReceive('getStatus')->andReturn([AlertRule::CRITICAL, 0]);

        $service = Mockery::mock(AlertRuleBehaviorRuleService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('findDependentAlertRules')
            ->with(['dep-1'])
            ->andReturn(collect([$dependentAlert]));

        $payload = [
            'alerts' => [
                ['labels' => ['instance' => 'api-server']],
            ],
        ];

        expect($service->silentRuleMatches($alertRule, $alertRule->rules[0], $payload, $now))->toBeTrue()
            ->and($service->silentRuleMatches($alertRule, $alertRule->rules[0], [
                'alerts' => [
                    ['labels' => ['instance' => 'other-server']],
                ],
            ], $now))->toBeFalse();
    });

    it('resolves silent when any silent rule matches', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'type' => AlertRuleType::PROMETHEUS,
            'endpointIds' => [],
            'rules' => [
                [
                    'id' => 'silent-1',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'filters' => [['key' => 'job', 'value' => 'nomatch*']],
                ],
                [
                    'id' => 'silent-2',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'filters' => [['key' => 'job', 'value' => 'prometheus*']],
                ],
            ],
        ]);

        $payload = [
            'alerts' => [
                ['labels' => ['job' => 'prometheus-main']],
            ],
        ];

        expect($this->service->resolveIsSilent($alertRule, $payload))->toBeTrue();
    });

    it('creates silent rules with optional filters and time bounds', function () {
        $alertRule = mockAlertRuleForPersistence([
            'rules' => [],
        ]);

        $rule = $this->service->createSilentRule($alertRule, [
            'name' => 'Maintenance silence',
            'filters' => [['key' => 'instance', 'value' => 'api*']],
            'startsAt' => 1_720_000_000,
            'endsAt' => 1_721_289_600,
        ]);

        expect($rule)->toMatchArray([
            'name' => 'Maintenance silence',
            'type' => AlertRuleBehaviorRuleType::SILENT->value,
            'filters' => [
                ['key' => 'instance', 'value' => 'api*'],
            ],
            'startsAt' => 1_720_000_000,
            'endsAt' => 1_721_289_600,
        ])->and($rule)->not->toHaveKey('dependsOnAlertRuleIds');
    });

    it('updates silent rules with merged optional fields', function () {
        $alertRule = mockAlertRuleForPersistence([
            'rules' => [
                [
                    'id' => 'silent-1',
                    'name' => 'Old name',
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                    'dependsOnAlertRuleIds' => ['dep-1'],
                    'triggerState' => AlertRule::CRITICAL,
                ],
            ],
        ]);

        $updated = $this->service->updateSilentRule($alertRule, 'silent-1', [
            'name' => 'New name',
            'filters' => [['key' => 'instance', 'value' => 'api*']],
            'endsAt' => 1_721_289_600,
        ]);

        expect($updated)->toMatchArray([
            'id' => 'silent-1',
            'name' => 'New name',
            'type' => AlertRuleBehaviorRuleType::SILENT->value,
            'dependsOnAlertRuleIds' => ['dep-1'],
            'triggerState' => AlertRule::CRITICAL,
            'filters' => [
                ['key' => 'instance', 'value' => 'api*'],
            ],
            'endsAt' => 1_721_289_600,
        ]);
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
