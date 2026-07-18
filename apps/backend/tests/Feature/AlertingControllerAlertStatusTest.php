<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertRule;
use App\Models\ApiAlertStatusHistory;
use App\Models\PrometheusHistory;
use Carbon\Carbon;
use Tests\Support\AlertStatusHistoryTestData;
use Tests\Support\TeamTestData;

describe('AlertingController AlertStatus', function () {
    beforeEach(function () {
        config([
            'cache.default' => 'array',
            'alert-status.timeline_slot_count' => 10,
        ]);

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

    it('accepts a single alertRuleId sent as a plain query string', function () {
        $this->apiAlert = AlertRule::create([
            'name' => 'API Alert',
            'type' => 'api',
            'userId' => $this->owner->id,
        ]);

        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/status?'.http_build_query([
                'fromTime' => $this->fromTime,
                'toTime' => $this->toTime,
            ]).'&alertRuleIds='.$this->apiAlert->id)
            ->assertSuccessful()
            ->assertJsonCount(1);
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

    it('accepts millisecond timestamps and returns second-based segments', function () {
        $this->apiAlert = AlertRule::create([
            'name' => 'API Alert',
            'type' => 'api',
            'userId' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/status?'.http_build_query([
                'alertRuleIds' => [$this->apiAlert->id],
                'fromTime' => $this->fromTime * 1000,
                'toTime' => $this->toTime * 1000,
            ]))
            ->assertSuccessful()
            ->assertJsonCount(1);

        $timeline = $response->json('0');

        expect($timeline['segments'][0]['fromTime'])->toBe($this->fromTime)
            ->and($timeline['segments'][array_key_last($timeline['segments'])]['toTime'])->toBe($this->toTime);
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
            ]))
            ->assertSuccessful()
            ->json();

        expect(collect($response)->pluck('alertRuleId')->all())
            ->toBe([$this->apiAlert->id]);
    });

    it('returns resolved segments spanning the whole window when an api alert has no history', function () {
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
            ]))
            ->assertSuccessful()
            ->json();

        $segments = $response[0]['segments'];

        expect($response[0]['bucketSeconds'])->toBe(100)
            ->and($segments)->toHaveCount(1)
            ->and($segments[0]['status'])->toBe('resolved')
            ->and($segments[0]['count'])->toBe(10)
            ->and(collect($segments)->sum('count'))->toBe(10);
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
            ]))
            ->assertSuccessful()
            ->json();

        $statuses = collect($response[0]['segments'])->pluck('status')->unique()->all();

        expect($statuses)->not->toContain('warning')
            ->and($statuses)->toContain('critical');
    });

    it('buckets a prometheus alert with worst-status-wins and segment summaries', function () {
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
            ->and(collect($segments)->sum('count'))->toBe(10)
            ->and($segments[0]['status'])->toBe('warning')
            ->and($segments[0]['count'])->toBe(2)
            ->and($segments[1]['status'])->toBe('critical')
            ->and($segments[1]['count'])->toBe(8)
            ->and($segments[1]['summary'])->toBeString()
            ->and($segments[1]['summary'])->not->toBeEmpty();
    });
});

describe('AlertingController AlertStatus timeline segments', function () {
    beforeEach(function () {
        config([
            'cache.default' => 'array',
            'alert-status.timeline_slot_count' => AlertStatusHistoryTestData::SLOT_COUNT,
        ]);

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->fromTime = AlertStatusHistoryTestData::WINDOW_FROM;
        $this->toTime = AlertStatusHistoryTestData::WINDOW_TO;
        $this->fireAt = AlertStatusHistoryTestData::FIRE_AT;
        $this->resolveAt = AlertStatusHistoryTestData::RESOLVE_AT;
        $this->createdAlerts = [];
    });

    afterEach(function () {
        foreach ($this->createdAlerts as $alert) {
            AlertStatusHistoryTestData::deleteAlertAndHistory($alert);
        }

        if (isset($this->owner)) {
            TeamTestData::deleteUser($this->owner);
        }
    });

    /**
     * @return array<string, array{0: string}>
     */
    function criticalResolvedAlertTypes(): array
    {
        return [
            'api' => ['api'],
            'grafana' => ['grafana'],
            'pmm' => ['pmm'],
            'elastic' => ['elastic'],
            'victoria_logs' => ['victoria_logs'],
            'prometheus' => ['prometheus'],
            'zabbix' => ['zabbix'],
            'sentry' => ['sentry'],
            'health' => ['health'],
        ];
    }

    function createTrackedAlert(string $type): AlertRule
    {
        $alert = AlertStatusHistoryTestData::createAlertRule(test()->owner, $type);
        $createdAlerts = test()->createdAlerts;
        $createdAlerts[] = $alert;
        test()->createdAlerts = $createdAlerts;

        return $alert;
    }

    function requestAlertStatus(AlertRule $alert): array
    {
        return test()->actingAs(test()->owner, 'api')
            ->getJson('/api/v1/alert-rule/status?'.http_build_query([
                'alertRuleIds' => [$alert->id],
                'fromTime' => test()->fromTime,
                'toTime' => test()->toTime,
            ]))
            ->assertSuccessful()
            ->json();
    }

    it('maps a single fire and resolve window into critical buckets for every critical-capable alert type', function (string $type) {
        $alert = createTrackedAlert($type);

        if ($type !== AlertRuleType::API->value) {
            AlertStatusHistoryTestData::seedResolvedBaseline($alert, $this->fromTime - 1);
        }

        AlertStatusHistoryTestData::seedFireEvent($alert, $this->fireAt);
        AlertStatusHistoryTestData::seedResolveEvent($alert, $this->resolveAt);

        $response = requestAlertStatus($alert);
        $segments = $response[0]['segments'];
        $expectedCriticalBuckets = AlertStatusHistoryTestData::bucketsOverlappingInterval(
            $this->fireAt,
            $this->resolveAt,
        );

        expect($response[0]['bucketSeconds'])->toBe(10_000)
            ->and(collect($segments)->sum('count'))->toBe(AlertStatusHistoryTestData::SLOT_COUNT)
            ->and($expectedCriticalBuckets)->not->toBeEmpty();

        foreach ($expectedCriticalBuckets as $bucket) {
            $midpoint = (int) floor(($bucket['fromTime'] + $bucket['toTime']) / 2);

            expect(AlertStatusHistoryTestData::segmentStatusAt($segments, $midpoint))
                ->toBe('critical', "Bucket {$bucket['fromTime']}->{$bucket['toTime']} should be critical for {$type}");
        }

        expect(AlertStatusHistoryTestData::segmentStatusAt($segments, $this->fromTime + 1))
            ->toBe('resolved');

        expect(AlertStatusHistoryTestData::segmentStatusAt($segments, $this->resolveAt + 10_000))
            ->toBe('resolved');
    })->with(fn () => criticalResolvedAlertTypes());

    it('marks the api alert example buckets as critical between 1782610000 and 1782660000', function () {
        $alert = createTrackedAlert('api');

        AlertStatusHistoryTestData::seedFireEvent($alert, $this->fireAt);
        AlertStatusHistoryTestData::seedResolveEvent($alert, $this->resolveAt);

        $response = requestAlertStatus($alert);
        $segments = $response[0]['segments'];

        $userExpectedCriticalBuckets = [
            ['fromTime' => 1_782_610_000, 'toTime' => 1_782_620_000],
            ['fromTime' => 1_782_620_000, 'toTime' => 1_782_630_000],
            ['fromTime' => 1_782_630_000, 'toTime' => 1_782_640_000],
            ['fromTime' => 1_782_640_000, 'toTime' => 1_782_650_000],
            ['fromTime' => 1_782_650_000, 'toTime' => 1_782_660_000],
        ];

        foreach ($userExpectedCriticalBuckets as $bucket) {
            $midpoint = (int) floor(($bucket['fromTime'] + $bucket['toTime']) / 2);

            expect(AlertStatusHistoryTestData::segmentStatusAt($segments, $midpoint))->toBe('critical');
        }

        $criticalSegment = collect($segments)->firstWhere('status', 'critical');

        expect($criticalSegment)->not->toBeNull()
            ->and($criticalSegment['fromTime'])->toBeLessThanOrEqual(1_782_610_000)
            ->and($criticalSegment['toTime'])->toBeGreaterThanOrEqual(1_782_660_000)
            ->and($criticalSegment['count'])->toBeGreaterThanOrEqual(5)
            ->and(collect($segments)->sum('count'))->toBe(AlertStatusHistoryTestData::SLOT_COUNT);
    });

    it('keeps the timeline resolved when only pre-window resolved history exists', function (string $type) {
        $alert = createTrackedAlert($type);

        AlertStatusHistoryTestData::seedResolvedBaseline($alert, $this->fromTime - 1);

        $response = requestAlertStatus($alert);
        $segments = $response[0]['segments'];

        expect(collect($segments)->pluck('status')->unique()->all())->toBe(['resolved'])
            ->and(collect($segments)->sum('count'))->toBe(AlertStatusHistoryTestData::SLOT_COUNT)
            ->and($segments[0]['fromTime'])->toBe($this->fromTime)
            ->and($segments[0]['toTime'])->toBe($this->toTime);
    })->with(fn () => criticalResolvedAlertTypes());

    it('stays critical through the end of the window when a fire event has no matching resolve', function (string $type) {
        $alert = createTrackedAlert($type);

        if ($type !== AlertRuleType::API->value) {
            AlertStatusHistoryTestData::seedResolvedBaseline($alert, $this->fromTime - 1);
        }

        AlertStatusHistoryTestData::seedFireEvent($alert, $this->fireAt);

        $response = requestAlertStatus($alert);
        $segments = $response[0]['segments'];

        expect(AlertStatusHistoryTestData::segmentStatusAt($segments, $this->toTime - 1))
            ->toBe('critical')
            ->and(AlertStatusHistoryTestData::segmentStatusAt($segments, $this->fromTime + 1))
            ->toBe('resolved');
    })->with(fn () => criticalResolvedAlertTypes());

    it('splits the timeline into alternating critical and resolved segments for repeated incidents', function (string $type) {
        $alert = createTrackedAlert($type);

        if ($type !== AlertRuleType::API->value) {
            AlertStatusHistoryTestData::seedResolvedBaseline($alert, $this->fromTime - 1);
        }

        $firstFireAt = $this->fromTime + 50_000;
        $firstResolveAt = $this->fromTime + 80_000;
        $secondFireAt = $this->fromTime + 200_000;
        $secondResolveAt = $this->fromTime + 230_000;

        AlertStatusHistoryTestData::seedFireEvent($alert, $firstFireAt);
        AlertStatusHistoryTestData::seedResolveEvent($alert, $firstResolveAt);
        AlertStatusHistoryTestData::seedFireEvent($alert, $secondFireAt);
        AlertStatusHistoryTestData::seedResolveEvent($alert, $secondResolveAt);

        $response = requestAlertStatus($alert);
        $segments = $response[0]['segments'];
        $statuses = collect($segments)->pluck('status')->all();

        expect($statuses)->toContain('critical')
            ->and($statuses)->toContain('resolved')
            ->and(collect($segments)->sum('count'))->toBe(AlertStatusHistoryTestData::SLOT_COUNT);

        foreach (AlertStatusHistoryTestData::bucketsOverlappingInterval($firstFireAt, $firstResolveAt) as $bucket) {
            $midpoint = (int) floor(($bucket['fromTime'] + $bucket['toTime']) / 2);

            expect(AlertStatusHistoryTestData::segmentStatusAt($segments, $midpoint))->toBe('critical');
        }

        foreach (AlertStatusHistoryTestData::bucketsOverlappingInterval($secondFireAt, $secondResolveAt) as $bucket) {
            $midpoint = (int) floor(($bucket['fromTime'] + $bucket['toTime']) / 2);

            expect(AlertStatusHistoryTestData::segmentStatusAt($segments, $midpoint))->toBe('critical');
        }

        $quietMidpoint = (int) floor(($firstResolveAt + $secondFireAt) / 2);

        expect(AlertStatusHistoryTestData::segmentStatusAt($segments, $quietMidpoint))->toBe('resolved');
    })->with(fn () => criticalResolvedAlertTypes());
});
