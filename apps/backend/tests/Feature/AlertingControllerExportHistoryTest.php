<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Exports\AlertHistoryExport;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
use App\Models\PrometheusHistory;
use App\Models\ZabbixWebhookAlert;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Support\TeamTestData;

describe('AlertingController ExportHistory', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->outsider = TeamTestData::createUser(Constants::ROLE_MEMBER);

        $this->from = Carbon::create(2026, 1, 1, 0, 0, 0)->timestamp;
        $this->to = $this->from + 3600;

        $this->apiAlert = AlertRule::create([
            'name' => 'API Export Alert',
            'type' => AlertRuleType::API,
            'userId' => $this->owner->id,
            'apiToken' => 'export-token-'.uniqid(),
        ]);
    });

    afterEach(function () {
        foreach (['apiAlert', 'prometheusAlert', 'zabbixAlert', 'splunkAlert', 'privateAlert'] as $property) {
            if (isset($this->{$property})) {
                ApiAlertHistory::query()->where('alertRuleId', $this->{$property}->_id)->delete();
                PrometheusHistory::query()->where('alertRuleId', $this->{$property}->_id)->delete();
                ZabbixWebhookAlert::query()->where('alertRuleId', $this->{$property}->_id)->delete();
                AlertRule::query()->where('_id', $this->{$property}->_id)->delete();
            }
        }

        foreach (['owner', 'outsider'] as $property) {
            if (isset($this->{$property})) {
                TeamTestData::deleteUser($this->{$property});
            }
        }
    });

    it('validates from and to timestamps', function () {
        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->apiAlert->id.'/export')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to']);
    });

    it('rejects a time window where to is not after from', function () {
        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->apiAlert->id.'/export?'.http_build_query([
                'from' => $this->to,
                'to' => $this->from,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    });

    it('rejects unsupported formats', function () {
        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->apiAlert->id.'/export?'.http_build_query([
                'from' => $this->from,
                'to' => $this->to,
                'format' => 'pdf',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format']);
    });

    it('rejects export for splunk alert rules', function () {
        $this->splunkAlert = AlertRule::create([
            'name' => 'Splunk Export Alert',
            'type' => AlertRuleType::SPLUNK,
            'userId' => $this->owner->id,
        ]);

        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->splunkAlert->id.'/export?'.http_build_query([
                'from' => $this->from,
                'to' => $this->to,
            ]))
            ->assertUnprocessable();
    });

    it('forbids readonly users from exporting private alerts', function () {
        $this->privateAlert = AlertRule::create([
            'name' => 'Private API Export Alert',
            'type' => AlertRuleType::API,
            'userId' => $this->owner->id,
            'isPrivate' => true,
        ]);

        $this->actingAs($this->outsider, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->privateAlert->id.'/export?'.http_build_query([
                'from' => $this->from,
                'to' => $this->to,
            ]))
            ->assertForbidden();
    });

    it('downloads csv history inside the time window', function () {
        Excel::fake();

        ApiAlertHistory::create([
            'alertRuleId' => $this->apiAlert->_id,
            'alertRuleName' => $this->apiAlert->name,
            'instance' => 'web-1',
            'description' => 'High CPU',
            'summary' => 'cpu > 90',
            'state' => ApiAlertHistory::FIRE,
            'createdAt' => Carbon::createFromTimestamp($this->from + 60),
        ]);

        ApiAlertHistory::create([
            'alertRuleId' => $this->apiAlert->_id,
            'alertRuleName' => $this->apiAlert->name,
            'instance' => 'web-outside',
            'description' => 'Old event',
            'summary' => 'outside window',
            'state' => ApiAlertHistory::RESOLVED,
            'createdAt' => Carbon::createFromTimestamp($this->from - 120),
        ]);

        $this->actingAs($this->owner, 'api')
            ->get('/api/v1/alert-rule/history/'.$this->apiAlert->id.'/export?'.http_build_query([
                'from' => $this->from,
                'to' => $this->to,
                'format' => 'csv',
            ]))
            ->assertSuccessful();

        Excel::assertDownloaded('alert-history-api-export-alert.csv', function (AlertHistoryExport $export) {
            $rows = $export->collection();

            expect($rows)->toHaveCount(1)
                ->and($rows->first()->instance)->toBe('web-1');

            $mapped = $export->map($rows->first());

            expect($export->headings())->toBe([
                'Alert Rule',
                'Instance',
                'Status',
                'Description',
                'Summary',
                'Created At (UTC)',
                'Created At (Jalali)',
            ])
                ->and($mapped)->toContain('web-1')
                ->and($mapped)->toContain('critical')
                ->and($mapped)->toContain('High CPU')
                ->and($mapped)->not->toContain('api');

            return true;
        });
    });

    it('downloads prometheus history', function () {
        Excel::fake();

        $this->prometheusAlert = AlertRule::create([
            'name' => 'Prometheus Export Alert',
            'type' => AlertRuleType::PROMETHEUS,
            'userId' => $this->owner->id,
        ]);

        PrometheusHistory::create([
            'alertRuleId' => $this->prometheusAlert->_id,
            'alertRuleName' => $this->prometheusAlert->name,
            'state' => PrometheusHistory::FIRE,
            'countFire' => 1,
            'countResolve' => 0,
            'alerts' => [[
                'labels' => [
                    'severity' => 'critical',
                    'alertname' => $this->prometheusAlert->name,
                    'instance' => 'db-1',
                ],
                'annotations' => [
                    'summary' => 'Database is down',
                ],
            ]],
            'createdAt' => Carbon::createFromTimestamp($this->from + 50),
        ]);

        $this->actingAs($this->owner, 'api')
            ->get('/api/v1/alert-rule/history/'.$this->prometheusAlert->id.'/export?'.http_build_query([
                'from' => $this->from,
                'to' => $this->to,
                'format' => 'csv',
            ]))
            ->assertSuccessful();

        Excel::assertDownloaded('alert-history-prometheus-export-alert.csv', function (AlertHistoryExport $export) {
            expect($export->collection())->toHaveCount(1);

            $mapped = $export->map($export->collection()->first());

            expect($export->headings())->toContain('Fire Count')
                ->and($export->headings())->toContain('Alert Name')
                ->and($mapped)->toContain('db-1')
                ->and($mapped)->toContain('critical')
                ->and($mapped)->toContain('Database is down')
                ->and($mapped)->toContain('1');

            return true;
        });
    });

    it('downloads zabbix history', function () {
        Excel::fake();

        $this->zabbixAlert = AlertRule::create([
            'name' => 'Zabbix Export Alert',
            'type' => AlertRuleType::ZABBIX,
            'userId' => $this->owner->id,
        ]);

        ZabbixWebhookAlert::create([
            'alertRuleId' => $this->zabbixAlert->_id,
            'alertRuleName' => $this->zabbixAlert->name,
            'host_name' => 'zbx-host-1',
            'event_status' => ZabbixWebhookAlert::PROBLEM,
            'event_severity' => 'High',
            'alert_subject' => 'CPU usage high',
            'alert_message' => 'CPU exceeded threshold',
            'createdAt' => Carbon::createFromTimestamp($this->from + 40),
        ]);

        $this->actingAs($this->owner, 'api')
            ->get('/api/v1/alert-rule/history/'.$this->zabbixAlert->id.'/export?'.http_build_query([
                'from' => $this->from,
                'to' => $this->to,
            ]))
            ->assertSuccessful();

        Excel::assertDownloaded('alert-history-zabbix-export-alert.xlsx', function (AlertHistoryExport $export) {
            $mapped = $export->map($export->collection()->first());

            expect($export->headings())->toContain('Host')
                ->and($export->headings())->toContain('Event Status')
                ->and($mapped)->toContain('zbx-host-1')
                ->and($mapped)->toContain('PROBLEM')
                ->and($mapped)->toContain('High')
                ->and($mapped)->toContain('CPU exceeded threshold');

            return true;
        });
    });

    it('accepts millisecond timestamps and downloads xlsx', function () {
        Excel::fake();

        ApiAlertHistory::create([
            'alertRuleId' => $this->apiAlert->_id,
            'alertRuleName' => $this->apiAlert->name,
            'instance' => 'db-1',
            'description' => 'Disk full',
            'summary' => 'disk > 95',
            'state' => ApiAlertHistory::FIRE,
            'createdAt' => Carbon::createFromTimestamp($this->from + 30),
        ]);

        $this->actingAs($this->owner, 'api')
            ->get('/api/v1/alert-rule/history/'.$this->apiAlert->id.'/export?'.http_build_query([
                'from' => $this->from * 1000,
                'to' => $this->to * 1000,
            ]))
            ->assertSuccessful();

        Excel::assertDownloaded('alert-history-api-export-alert.xlsx', function (AlertHistoryExport $export) {
            expect($export->collection())->toHaveCount(1)
                ->and($export->collection()->first()->instance)->toBe('db-1');

            return true;
        });
    });
});
