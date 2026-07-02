<?php

use App\Enums\Constants;
use App\Models\AlertRule;
use App\Models\ApiAlertStatusHistory;
use App\Models\PrometheusHistory;
use Carbon\Carbon;
use Tests\Support\TeamTestData;

describe('AlertingController AlertStatus', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->outsider = TeamTestData::createUser(Constants::ROLE_MEMBER);

        $this->fromTime = Carbon::create(2026, 1, 1, 0, 0, 0)->timestamp;
        $this->toTime = $this->fromTime + 1000;
    });

    afterEach(function () {
        foreach (['apiAlert', 'prometheusAlert', 'privateAlert'] as $property) {
            if (isset($this->{$property})) {
                PrometheusHistory::query()->where('alertRuleId', $this->{$property}->_id)->delete();
                ApiAlertStatusHistory::query()->where('alertRuleId', $this->{$property}->_id)->delete();
                AlertRule::query()->where('_id', $this->{$property}->_id)->delete();
            }
        }

        foreach (['owner', 'outsider'] as $property) {
            if (isset($this->{$property})) {
                TeamTestData::deleteUser($this->{$property});
            }
        }
    });

    it('validates the request payload', function () {
        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/status')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['alertRuleIds', 'fromTime', 'toTime']);
    });

    it('rejects a time window where toTime is not after fromTime', function () {
        $this->apiAlert = AlertRule::create([
            'name' => 'API Alert',
            'type' => 'api',
            'userId' => $this->owner->id,
        ]);

        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/status?'.http_build_query([
                'alertRuleIds' => [$this->apiAlert->id],
                'fromTime' => $this->toTime,
                'toTime' => $this->fromTime,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['toTime']);
    });

    it('silently excludes alert rules the requesting user cannot access', function () {
        $this->privateAlert = AlertRule::create([
            'name' => 'Private Alert',
            'type' => 'api',
            'userId' => $this->owner->id,
        ]);

        $this->apiAlert = AlertRule::create([
            'name' => 'Outsider Alert',
            'type' => 'api',
            'userId' => $this->outsider->id,
        ]);

        $response = $this->actingAs($this->outsider, 'api')
            ->getJson('/api/v1/alert-rule/status?'.http_build_query([
                'alertRuleIds' => [$this->privateAlert->id, $this->apiAlert->id],
                'fromTime' => $this->fromTime,
                'toTime' => $this->toTime,
                'bucketCount' => 10,
            ]))
            ->assertSuccessful()
            ->json();

        expect(collect($response)->pluck('alertRuleId')->all())
            ->toBe([$this->apiAlert->id]);
    });

    it('returns unknown segments spanning the whole window when there is no history', function () {
        $this->apiAlert = AlertRule::create([
            'name' => 'No History Alert',
            'type' => 'api',
            'userId' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/status?'.http_build_query([
                'alertRuleIds' => [$this->apiAlert->id],
                'fromTime' => $this->fromTime,
                'toTime' => $this->toTime,
                'bucketCount' => 10,
            ]))
            ->assertSuccessful()
            ->json();

        $segments = $response[0]['segments'];

        expect($response[0]['bucketSeconds'])->toBe(100)
            ->and($segments)->toHaveCount(10)
            ->and(collect($segments)->pluck('status')->unique()->all())->toBe(['unknown']);
    });

    it('never reports a warning status for API alerts, which have no severity concept', function () {
        $this->apiAlert = AlertRule::create([
            'name' => 'API Alert',
            'type' => 'api',
            'userId' => $this->owner->id,
        ]);

        ApiAlertStatusHistory::create([
            'alertRuleId' => $this->apiAlert->_id,
            'state' => ApiAlertStatusHistory::FIRE,
            'countAlerts' => 1,
            'firedInstances' => [['instance' => 'host-1', 'description' => 'connection refused']],
            'createdAt' => Carbon::createFromTimestamp($this->fromTime + 50),
        ]);

        $response = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/status?'.http_build_query([
                'alertRuleIds' => [$this->apiAlert->id],
                'fromTime' => $this->fromTime,
                'toTime' => $this->toTime,
                'bucketCount' => 10,
            ]))
            ->assertSuccessful()
            ->json();

        $statuses = collect($response[0]['segments'])->pluck('status')->unique()->all();

        expect($statuses)->not->toContain('warning')
            ->and($statuses)->toContain('critical');
    });

    it('buckets a prometheus alert with worst-status-wins and nested raw events', function () {
        $this->prometheusAlert = AlertRule::create([
            'name' => 'DatabaseDown',
            'type' => 'prometheus',
            'userId' => $this->owner->id,
        ]);

        PrometheusHistory::create([
            'alertRuleId' => $this->prometheusAlert->_id,
            'state' => PrometheusHistory::FIRE,
            'countFire' => 1,
            'alerts' => [[
                'labels' => ['severity' => 'warning', 'alertname' => 'HighLatency'],
                'annotations' => ['summary' => 'Latency is elevated'],
            ]],
            'createdAt' => Carbon::createFromTimestamp($this->fromTime + 50),
        ]);

        PrometheusHistory::create([
            'alertRuleId' => $this->prometheusAlert->_id,
            'state' => PrometheusHistory::FIRE,
            'countFire' => 2,
            'alerts' => [[
                'labels' => ['severity' => 'critical', 'alertname' => 'HighLatency'],
                'annotations' => ['summary' => 'Latency is very high'],
            ]],
            'createdAt' => Carbon::createFromTimestamp($this->fromTime + 250),
        ]);

        $response = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/status?'.http_build_query([
                'alertRuleIds' => [$this->prometheusAlert->id],
                'fromTime' => $this->fromTime,
                'toTime' => $this->toTime,
                'bucketCount' => 10,
            ]))
            ->assertSuccessful()
            ->json();

        $segments = $response[0]['segments'];

        expect($response[0]['bucketSeconds'])->toBe(100)
            ->and($segments)->toHaveCount(10)
            ->and($segments[0]['status'])->toBe('warning')
            ->and($segments[1]['status'])->toBe('warning')
            ->and($segments[2]['status'])->toBe('critical')
            ->and($segments[9]['status'])->toBe('critical');

        $criticalEvent = collect($segments[2]['events'])->firstWhere('status', 'critical');

        expect($criticalEvent)->not->toBeNull()
            ->and($criticalEvent['summary'])->toBeString()
            ->and($criticalEvent['summary'])->not->toBeEmpty();
    });
});
