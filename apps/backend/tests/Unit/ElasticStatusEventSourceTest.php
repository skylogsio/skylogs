<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertRule;
use App\Models\ElasticCheck;
use App\Models\ElasticHistory;
use App\Services\AlertStatus\AlertStatusTimelineBuilder;
use App\Services\AlertStatus\Sources\ElasticStatusEventSource;
use Carbon\Carbon;
use Tests\Support\TeamTestData;

describe('ElasticStatusEventSource', function () {
    beforeEach(function () {
        $this->source = new ElasticStatusEventSource;
        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->fromTime = 1_782_000_000;
        $this->toTime = 1_783_000_000;
    });

    afterEach(function () {
        if (isset($this->alertRule)) {
            ElasticHistory::query()->where('alertRuleId', $this->alertRule->_id)->delete();
            ElasticCheck::query()->where('alertRuleId', $this->alertRule->_id)->delete();
            AlertRule::query()->where('_id', $this->alertRule->_id)->delete();
        }

        if (isset($this->owner)) {
            TeamTestData::deleteUser($this->owner);
        }
    });

    it('defaults the baseline to resolved when an elastic alert has no prior history', function () {
        $this->alertRule = AlertRule::create([
            'name' => 'Elastic Baseline Test',
            'type' => AlertRuleType::ELASTIC->value,
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
            'name' => 'Elastic Reconcile Test',
            'type' => AlertRuleType::ELASTIC->value,
            'userId' => $this->owner->id,
            'queryString' => 'level:error',
            'countDocument' => 10,
            'minutes' => 5,
            'dataviewTitle' => 'logs-test',
        ]);

        ElasticHistory::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => ElasticHistory::RESOLVED,
            'queryString' => 'level:error',
            'countDocument' => 10,
            'currentCountDocument' => 0,
            'minutes' => 5,
            'dataviewTitle' => 'logs-test',
            'createdAt' => Carbon::createFromTimestamp($this->fromTime - 86_400),
        ]);

        ElasticHistory::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => ElasticHistory::FIRE,
            'queryString' => 'level:error',
            'countDocument' => 10,
            'currentCountDocument' => 15,
            'minutes' => 5,
            'dataviewTitle' => 'logs-test',
            'createdAt' => Carbon::createFromTimestamp($this->fromTime + 50_000),
        ]);

        ElasticCheck::create([
            'alertRuleId' => $this->alertRule->_id,
            'queryString' => 'level:error',
            'countDocument' => 10,
            'currentCountDocument' => 15,
            'minutes' => 5,
            'dataviewTitle' => 'logs-test',
            'state' => ElasticCheck::FIRE,
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
});
