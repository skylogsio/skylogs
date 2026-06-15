<?php

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\GrafanaWebhookAlert;
use App\Models\Notify;
use App\Models\PrometheusCheck;
use App\Services\AlertMessage\AlertMessageTemplateRenderer;
use App\Services\NotifyMessageComposer;
use Tests\Support\Factories\AlertRuleFactory;

/**
 * @return array<string, mixed>
 */
function prometheus_test_payload(): array
{
    return [
        'state' => PrometheusCheck::FIRE,
        'alerts' => [
            [
                'skylogsStatus' => PrometheusCheck::FIRE,
                'dataSourceName' => 'Prom DS',
                'labels' => [
                    'alertname' => 'HighCPU',
                    'namespace' => 'prod',
                    'pod' => 'api-1',
                    'reason' => 'ThresholdExceeded',
                    'severity' => 'critical',
                    'job' => 'kube',
                ],
                'annotations' => [
                    'summary' => 'CPU is high',
                    'description' => 'Pod api-1 CPU above 90%',
                ],
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function grafana_test_payload(): array
{
    return [
        'status' => GrafanaWebhookAlert::FIRING,
        'dataSourceName' => 'Grafana DS',
        'alerts' => [
            [
                'status' => GrafanaWebhookAlert::FIRING,
                'labels' => [
                    'alertname' => 'DiskFull',
                    'instance' => 'host-a',
                    'job' => 'node',
                    'severity' => 'warning',
                ],
                'annotations' => [
                    'summary' => 'Disk almost full',
                    'description' => 'Mount /data at 95%',
                    'runbook_url' => 'https://example.test/runbook',
                ],
            ],
        ],
    ];
}

describe('AlertMessageTemplateRenderer', function () {
    it('renders prometheus label and alert_items placeholders', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'CPU Alert',
            'type' => AlertRuleType::PROMETHEUS,
            'state' => AlertRule::CRITICAL,
            'fireCount' => 1,
        ]);

        $body = AlertMessageTemplateRenderer::make()->renderFromPayload(
            $rule,
            prometheus_test_payload(),
            "{{name}}\n{{label.alertname}}\n{{alert_items labels=\"pod\" annotations=\"summary\"}}",
        );

        expect($body)
            ->toContain('CPU Alert')
            ->toContain('HighCPU')
            ->toContain('pod : api-1')
            ->toContain('summary : CPU is high')
            ->not->toContain('namespace :');
    });

    it('renders prometheus exclude_labels in alert_items', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'CPU Alert',
            'type' => AlertRuleType::PROMETHEUS,
        ]);

        $body = AlertMessageTemplateRenderer::make()->renderFromPayload(
            $rule,
            prometheus_test_payload(),
            '{{alert_items labels="*" exclude_labels="job"}}',
        );

        expect($body)
            ->toContain('alertname : HighCPU')
            ->toContain('pod : api-1')
            ->not->toContain('job :');
    });

    it('renders grafana labels exclude block', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'Disk Alert',
            'type' => AlertRuleType::GRAFANA,
        ]);

        $body = AlertMessageTemplateRenderer::make()->renderFromPayload(
            $rule,
            grafana_test_payload(),
            "{{labels:* exclude=job}}\n{{annotations:summary}}",
        );

        expect($body)
            ->toContain('alertname : DiskFull')
            ->toContain('instance : host-a')
            ->toContain('summary : Disk almost full')
            ->not->toContain('job :')
            ->not->toContain('runbook_url :');
    });

    it('renders pmm alerts using the grafana builder', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'PMM Alert',
            'type' => AlertRuleType::PMM,
        ]);

        $body = AlertMessageTemplateRenderer::make()->renderFromPayload(
            $rule,
            grafana_test_payload(),
            '{{name}}|{{dataSourceName}}|{{label.alertname}}',
        );

        expect($body)->toBe('PMM Alert|Grafana DS|DiskFull');
    });

    it('resolves payload from notify alert at send time', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'CPU Alert',
            'type' => AlertRuleType::PROMETHEUS,
        ]);

        $notify = Notify::withoutEvents(fn () => new Notify([
            'alert' => prometheus_test_payload(),
            'messages' => [
                'body' => 'stored default',
                'overrides' => [],
            ],
        ]));

        $body = AlertMessageTemplateRenderer::make()->render(
            $rule,
            $notify,
            '{{name}} fired: {{label.alertname}} on {{label.pod}}',
        );

        expect($body)->toBe('CPU Alert fired: HighCPU on api-1');
    });

    it('renders prometheus default template matching legacy structure', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'CPU Alert',
            'type' => AlertRuleType::PROMETHEUS,
        ]);

        $body = AlertMessageTemplateRenderer::make()->renderDefault($rule, prometheus_test_payload());

        expect($body)
            ->toContain("CPU Alert\n\n")
            ->toContain("State: Fire 🔥\n\n")
            ->toContain("Fire 🔥\n")
            ->toContain("Data Source: Prom DS\n")
            ->toContain("alertname : HighCPU\n")
            ->toContain("namespace : prod\n")
            ->toContain("pod : api-1\n")
            ->toContain("summary : CPU is high\n")
            ->toContain("description : Pod api-1 CPU above 90%\n")
            ->toContain("************\n\n")
            ->toMatch('/date: \d{4}\/\d{2}\/\d{2}$/');
    });

    it('renders grafana default template matching legacy structure', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'Disk Alert',
            'type' => AlertRuleType::GRAFANA,
        ]);

        $body = AlertMessageTemplateRenderer::make()->renderDefault($rule, grafana_test_payload());

        expect($body)
            ->toContain("Disk Alert\n\n")
            ->toContain("State: Firing 🔥\n\n")
            ->toContain("Data Source: Grafana DS\n\n")
            ->toContain("Warning ⚠️\n")
            ->toContain("alertname : DiskFull\n")
            ->toContain("instance : host-a\n")
            ->toContain("job : node\n")
            ->toContain("summary : Disk almost full\n")
            ->toContain("description : Mount /data at 95%\n")
            ->not->toContain('runbook_url :')
            ->toMatch('/Date: \d{4}\/\d{2}\/\d{2}$/');
    });
});

describe('PrometheusCheck defaultMessage', function () {
    it('uses the shared default template renderer', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'CPU Alert',
            'type' => AlertRuleType::PROMETHEUS,
        ]);

        $check = PrometheusCheck::withoutEvents(function () {
            $model = new PrometheusCheck;
            $model->forceFill(prometheus_test_payload());

            return $model;
        });
        $check->setRelation('alertRule', $rule);

        $expected = AlertMessageTemplateRenderer::make()->renderDefault($rule, prometheus_test_payload());

        expect($check->defaultMessage())->toBe($expected);
    });
});

describe('GrafanaWebhookAlert defaultMessage', function () {
    it('uses the shared default template renderer', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'Disk Alert',
            'type' => AlertRuleType::GRAFANA,
        ]);

        $alert = GrafanaWebhookAlert::withoutEvents(function () {
            $model = new GrafanaWebhookAlert;
            $model->forceFill(grafana_test_payload());

            return $model;
        });
        $alert->setRelation('alertRule', $rule);

        $expected = AlertMessageTemplateRenderer::make()->renderDefault($rule, grafana_test_payload());

        expect($alert->defaultMessage())->toBe($expected);
    });
});

describe('NotifyMessageComposer prometheus templates', function () {
    it('preserves telegram meta when applying prometheus template via notify', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'CPU Alert',
            'type' => AlertRuleType::PROMETHEUS,
            'showAcknowledgeBtn' => true,
        ]);

        $check = PrometheusCheck::withoutEvents(function () {
            $model = new PrometheusCheck;
            $model->forceFill([
                ...prometheus_test_payload(),
                'alertRuleId' => '507f1f77bcf86cd799439011',
            ]);

            return $model;
        });
        $check->setRelation('alertRule', $rule);

        $notify = Notify::withoutEvents(fn () => new Notify([
            'alert' => $check->toArray(),
            'messages' => NotifyMessageComposer::fromMessageable($check)->toArray(),
        ]));

        $payload = NotifyMessageComposer::composeFromSingleTemplate(
            $rule,
            $notify,
            '{{name}} on {{label.pod}}',
        );

        expect($payload->telegram())->toBeArray()
            ->and($payload->telegram()['message'])->toBe('CPU Alert on api-1')
            ->and($payload->telegram()['meta'][0]['text'] ?? null)->toBe('Acknowledge');
    });
});
