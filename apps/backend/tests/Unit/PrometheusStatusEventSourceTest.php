<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertRule;
use App\Models\PrometheusCheck;
use App\Models\PrometheusHistory;
use App\Services\AlertStatus\AlertStatusTimelineBuilder;
use App\Services\AlertStatus\Sources\PrometheusStatusEventSource;
use Carbon\Carbon;
use Tests\Support\TeamTestData;

describe('PrometheusStatusEventSource', function () {
    beforeEach(function () {
        $this->source = new PrometheusStatusEventSource;
        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->fromTime = 1_782_000_000;
        $this->toTime = 1_783_000_000;
    });

    afterEach(function () {
        if (isset($this->alertRule)) {
            PrometheusHistory::query()->where('alertRuleId', $this->alertRule->_id)->delete();
            PrometheusCheck::query()->where('alertRuleId', $this->alertRule->_id)->delete();
            AlertRule::query()->where('_id', $this->alertRule->_id)->delete();
        }

        if (isset($this->owner)) {
            TeamTestData::deleteUser($this->owner);
        }
    });

    it('defaults the baseline to resolved when a prometheus alert has no prior history', function () {
        $this->alertRule = AlertRule::create([
            'name' => 'Prometheus Baseline Test',
            'type' => AlertRuleType::PROMETHEUS->value,
            'userId' => $this->owner->id,
        ]);

        $baseline = $this->source->fetchBaseline(
            collect([(string) $this->alertRule->_id => $this->alertRule]),
            Carbon::createFromTimestamp($this->fromTime),
        );

        expect($baseline->get((string) $this->alertRule->_id)->status)->toBe('resolved');
    });

    it('reconciles a firing check when the latest stored history before the window is resolved', function () {
        $this->alertRule = AlertRule::create([
            'name' => 'Prometheus Reconcile Test',
            'type' => AlertRuleType::PROMETHEUS->value,
            'userId' => $this->owner->id,
        ]);

        $firingAlerts = [[
            'labels' => ['severity' => 'critical', 'alertname' => 'DatabaseDown'],
            'annotations' => ['summary' => 'Database is down'],
            'skylogsStatus' => PrometheusCheck::FIRE,
        ]];

        PrometheusHistory::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => PrometheusHistory::RESOLVED,
            'alerts' => [],
            'countFire' => 0,
            'createdAt' => Carbon::createFromTimestamp($this->fromTime - 86_400),
        ]);

        PrometheusHistory::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => PrometheusHistory::FIRE,
            'alerts' => $firingAlerts,
            'countFire' => 1,
            'createdAt' => Carbon::createFromTimestamp($this->fromTime + 50_000),
        ]);

        PrometheusCheck::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => PrometheusCheck::FIRE,
            'alerts' => $firingAlerts,
            'updatedAt' => Carbon::createFromTimestamp($this->fromTime + 50_000),
        ]);

        $from = Carbon::createFromTimestamp($this->fromTime);
        $to = Carbon::createFromTimestamp($this->toTime);
        $alertRules = collect([(string) $this->alertRule->_id => $this->alertRule]);

        $events = collect([
            $this->source->fetchBaseline($alertRules, $from)->get((string) $this->alertRule->_id),
        ])->merge(
            $this->source->fetchEvents($alertRules, $from, $to),
        );

        $timeline = (new AlertStatusTimelineBuilder)->build($events, $this->fromTime, $this->toTime, 100);
        $statuses = collect($timeline['segments'])->pluck('status')->unique()->values()->all();

        expect($statuses)->toContain('critical');
    });

    it('reconciles warning severity from the current check when all firing alerts are warnings', function () {
        $this->alertRule = AlertRule::create([
            'name' => 'Prometheus Warning Reconcile Test',
            'type' => AlertRuleType::PROMETHEUS->value,
            'userId' => $this->owner->id,
        ]);

        $warningAlerts = [[
            'labels' => ['severity' => 'warning', 'alertname' => 'HighLatency'],
            'annotations' => ['summary' => 'Latency is elevated'],
            'skylogsStatus' => PrometheusCheck::FIRE,
        ]];

        PrometheusCheck::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => PrometheusCheck::FIRE,
            'alerts' => $warningAlerts,
            'updatedAt' => Carbon::createFromTimestamp($this->fromTime + 50_000),
        ]);

        $from = Carbon::createFromTimestamp($this->fromTime);
        $to = Carbon::createFromTimestamp($this->toTime);
        $alertRules = collect([(string) $this->alertRule->_id => $this->alertRule]);

        $events = collect([
            $this->source->fetchBaseline($alertRules, $from)->get((string) $this->alertRule->_id),
        ])->merge(
            $this->source->fetchEvents($alertRules, $from, $to),
        );

        $timeline = (new AlertStatusTimelineBuilder)->build($events, $this->fromTime, $this->toTime, 100);
        $statuses = collect($timeline['segments'])->pluck('status')->unique()->values()->all();

        expect($statuses)->toContain('warning');
    });
});
