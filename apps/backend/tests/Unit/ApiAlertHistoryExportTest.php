<?php

use App\Enums\AlertRuleType;
use App\Exports\AlertHistoryExport;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
use App\Models\ElasticHistory;
use App\Models\GrafanaWebhookAlert;
use App\Models\HealthHistory;
use App\Models\MetabaseWebhookAlert;
use App\Models\PrometheusHistory;
use App\Models\SentryWebhookAlert;
use App\Models\VictoriaLogsHistory;
use App\Models\ZabbixWebhookAlert;

function historyExportFor(AlertRuleType $type, string $name = 'Export Alert'): AlertHistoryExport
{
    $alert = new AlertRule([
        'name' => $name,
        'type' => $type,
    ]);

    return new AlertHistoryExport($alert, collect());
}

it('uses api columns without a type field', function () {
    $export = historyExportFor(AlertRuleType::API, 'API Export Alert');

    expect($export->headings())->toBe([
        'Alert Rule',
        'Instance',
        'Status',
        'Description',
        'Summary',
        'Created At (UTC)',
        'Created At (Jalali)',
    ]);

    $fire = new ApiAlertHistory([
        'alertRuleName' => 'API Export Alert',
        'instance' => 'web-1',
        'description' => 'High CPU',
        'summary' => 'cpu > 90',
        'state' => ApiAlertHistory::FIRE,
    ]);

    expect($export->map($fire))->toContain('web-1')
        ->and($export->map($fire))->toContain('critical')
        ->and($export->map($fire))->toContain('High CPU')
        ->and($export->map($fire))->not->toContain('api');
});

it('uses prometheus snapshot columns', function () {
    $export = historyExportFor(AlertRuleType::PROMETHEUS);

    expect($export->headings())->toContain('Fire Count')
        ->and($export->headings())->toContain('Alert Name')
        ->and($export->headings())->not->toContain('Query');

    $history = new PrometheusHistory([
        'alertRuleName' => 'Export Alert',
        'state' => PrometheusHistory::FIRE,
        'countFire' => 2,
        'countResolve' => 1,
        'alerts' => [[
            'labels' => [
                'instance' => 'db-1',
                'alertname' => 'Export Alert',
                'severity' => 'critical',
            ],
            'annotations' => [
                'summary' => 'Database is down',
                'description' => 'Primary replica unreachable',
            ],
        ]],
    ]);

    $mapped = $export->map($history);

    expect($mapped)->toContain('db-1')
        ->and($mapped)->toContain('Export Alert')
        ->and($mapped)->toContain('critical')
        ->and($mapped)->toContain('2')
        ->and($mapped)->toContain('1')
        ->and($mapped)->toContain('Database is down')
        ->and($mapped)->toContain('Primary replica unreachable');
});

it('uses grafana webhook columns', function () {
    $export = historyExportFor(AlertRuleType::GRAFANA);

    expect($export->headings())->toContain('Title')
        ->and($export->headings())->toContain('Data Source');

    $history = new GrafanaWebhookAlert([
        'alertRuleName' => 'Export Alert',
        'status' => GrafanaWebhookAlert::FIRING,
        'title' => 'Service down',
        'message' => 'Multiple series firing',
        'dataSourceName' => 'grafana-prod',
        'alerts' => [[
            'labels' => [
                'instance' => 'web-west',
                'alertname' => 'Export Alert',
                'severity' => 'critical',
            ],
            'annotations' => [
                'description' => 'Service is unavailable',
                'summary' => 'HTTP 5xx',
            ],
        ]],
    ]);

    $mapped = $export->map($history);

    expect($mapped)->toContain('firing')
        ->and($mapped)->toContain('web-west')
        ->and($mapped)->toContain('Service down')
        ->and($mapped)->toContain('grafana-prod')
        ->and($mapped)->toContain('HTTP 5xx');
});

it('uses elastic query and count columns', function () {
    $export = historyExportFor(AlertRuleType::ELASTIC);

    expect($export->headings())->toContain('Query')
        ->and($export->headings())->toContain('Dataview')
        ->and($export->headings())->toContain('Threshold Count');

    $history = new ElasticHistory([
        'alertRuleName' => 'Export Alert',
        'queryString' => 'level:error',
        'dataviewTitle' => 'logs-test',
        'conditionType' => 'greaterOrEqual',
        'countDocument' => 10,
        'currentCountDocument' => 15,
        'minutes' => 5,
        'state' => ElasticHistory::FIRE,
    ]);

    $mapped = $export->map($history);

    expect($mapped)->toContain('level:error')
        ->and($mapped)->toContain('logs-test')
        ->and($mapped)->toContain('greaterOrEqual')
        ->and($mapped)->toContain('10')
        ->and($mapped)->toContain('15')
        ->and($mapped)->toContain('critical');
});

it('uses victoria logs columns without a dataview', function () {
    $export = historyExportFor(AlertRuleType::VICTORIA_LOGS);

    expect($export->headings())->toContain('Query')
        ->and($export->headings())->not->toContain('Dataview');

    $history = new VictoriaLogsHistory([
        'alertRuleName' => 'Export Alert',
        'queryString' => 'status:failed',
        'conditionType' => 'greaterOrEqual',
        'countDocument' => 5,
        'currentCountDocument' => 8,
        'minutes' => 5,
        'state' => VictoriaLogsHistory::RESOLVED,
    ]);

    $mapped = $export->map($history);

    expect($mapped)->toContain('status:failed')
        ->and($mapped)->toContain('resolved')
        ->and($mapped)->toContain('8');
});

it('uses zabbix event columns', function () {
    $export = historyExportFor(AlertRuleType::ZABBIX);

    expect($export->headings())->toContain('Host')
        ->and($export->headings())->toContain('Event Status')
        ->and($export->headings())->toContain('Event Severity');

    $history = new ZabbixWebhookAlert([
        'alertRuleName' => 'Export Alert',
        'host_name' => 'zbx-host-1',
        'event_id' => '12345',
        'event_status' => ZabbixWebhookAlert::PROBLEM,
        'event_severity' => 'High',
        'alert_subject' => 'CPU usage high',
        'alert_message' => 'CPU exceeded threshold',
        'dataSourceName' => 'zabbix-prod',
    ]);

    $mapped = $export->map($history);

    expect($mapped)->toContain('PROBLEM')
        ->and($mapped)->toContain('High')
        ->and($mapped)->toContain('zbx-host-1')
        ->and($mapped)->toContain('12345')
        ->and($mapped)->toContain('CPU usage high')
        ->and($mapped)->toContain('CPU exceeded threshold')
        ->and($mapped)->toContain('zabbix-prod');
});

it('uses sentry issue columns', function () {
    $export = historyExportFor(AlertRuleType::SENTRY);

    expect($export->headings())->toContain('Action')
        ->and($export->headings())->toContain('URL');

    $history = new SentryWebhookAlert([
        'alertRuleName' => 'Export Alert',
        'action' => AlertRule::CRITICAL,
        'title' => 'Unhandled exception',
        'message' => 'Null pointer in worker',
        'description' => 'Stack trace omitted',
        'url' => 'https://sentry.example/issues/1',
        'project_name' => 'backend',
        'dataSourceName' => 'sentry-prod',
        'dataSourceAlertName' => 'worker-crash',
    ]);

    $mapped = $export->map($history);

    expect($mapped)->toContain('critical')
        ->and($mapped)->toContain('Unhandled exception')
        ->and($mapped)->toContain('Null pointer in worker')
        ->and($mapped)->toContain('https://sentry.example/issues/1')
        ->and($mapped)->toContain('backend')
        ->and($mapped)->toContain('worker-crash');
});

it('uses health check columns', function () {
    $export = historyExportFor(AlertRuleType::HEALTH);

    expect($export->headings())->toContain('URL')
        ->and($export->headings())->toContain('Counter')
        ->and($export->headings())->toContain('Threshold');

    $down = new HealthHistory([
        'alertRuleName' => 'Export Alert',
        'url' => 'https://health-test.example.com',
        'checkType' => 'http',
        'state' => HealthHistory::DOWN,
        'counter' => 3,
        'threshold' => 3,
    ]);

    $up = new HealthHistory([
        'alertRuleName' => 'Export Alert',
        'url' => 'https://health-test.example.com',
        'state' => HealthHistory::UP,
        'counter' => 0,
        'threshold' => 3,
    ]);

    expect($export->map($down))->toContain('down')
        ->and($export->map($down))->toContain('https://health-test.example.com')
        ->and($export->map($down))->toContain('3')
        ->and($export->map($up))->toContain('up');
});

it('uses metabase question columns', function () {
    $export = historyExportFor(AlertRuleType::METABASE);

    expect($export->headings())->toContain('Question Name')
        ->and($export->headings())->toContain('Question URL');

    $history = new MetabaseWebhookAlert([
        'alertRuleName' => 'Export Alert',
        'question_name' => 'Failed payments',
        'alert_name' => 'Failed payments',
        'question_url' => 'https://metabase.example/question/9',
        'alert_creator_name' => 'ops',
        'type' => 'question',
    ]);

    $mapped = $export->map($history);

    expect($mapped)->toContain('triggered')
        ->and($mapped)->toContain('Failed payments')
        ->and($mapped)->toContain('https://metabase.example/question/9')
        ->and($mapped)->toContain('ops');
});
