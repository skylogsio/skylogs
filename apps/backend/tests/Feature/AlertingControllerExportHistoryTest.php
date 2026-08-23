<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Exports\ApiAlertHistoryExport;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
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
        foreach (['apiAlert', 'prometheusAlert', 'privateAlert'] as $property) {
            if (isset($this->{$property})) {
                ApiAlertHistory::query()->where('alertRuleId', $this->{$property}->_id)->delete();
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

    it('rejects export for non-api alert rules', function () {
        $this->prometheusAlert = AlertRule::create([
            'name' => 'Prometheus Export Alert',
            'type' => AlertRuleType::PROMETHEUS,
            'userId' => $this->owner->id,
        ]);

        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->prometheusAlert->id.'/export?'.http_build_query([
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

        Excel::assertDownloaded('alert-history-api-export-alert.csv', function (ApiAlertHistoryExport $export) {
            $rows = $export->collection();

            expect($rows)->toHaveCount(1)
                ->and($rows->first()->instance)->toBe('web-1');

            $mapped = $export->map($rows->first());

            expect($mapped)->toContain('web-1')
                ->and($mapped)->toContain('critical')
                ->and($mapped)->toContain('High CPU');

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

        Excel::assertDownloaded('alert-history-api-export-alert.xlsx', function (ApiAlertHistoryExport $export) {
            expect($export->collection())->toHaveCount(1)
                ->and($export->collection()->first()->instance)->toBe('db-1');

            return true;
        });
    });
});
