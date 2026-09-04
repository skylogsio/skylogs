<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertRule;
use App\Models\VictoriaLogsCheck;
use App\Models\VictoriaLogsHistory;
use App\Services\AlertStatus\AlertStatusTimelineBuilder;
use App\Services\AlertStatus\Sources\VictoriaLogsStatusEventSource;
use Carbon\Carbon;
use Tests\Support\TeamTestData;

describe('VictoriaLogsStatusEventSource', function () {
    beforeEach(function () {
        $this->source = new VictoriaLogsStatusEventSource;
        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->fromTime = 1_782_000_000;
        $this->toTime = 1_783_000_000;
    });

    afterEach(function () {
        if (isset($this->alertRule)) {
            VictoriaLogsHistory::query()->where('alertRuleId', $this->alertRule->_id)->delete();
            VictoriaLogsCheck::query()->where('alertRuleId', $this->alertRule->_id)->delete();
            AlertRule::query()->where('_id', $this->alertRule->_id)->delete();
        }

        if (isset($this->owner)) {
            TeamTestData::deleteUser($this->owner);
        }
    });

    it('defaults the baseline to resolved when a victoria logs alert has no prior history', function () {
        $this->alertRule = AlertRule::create([
            'name' => 'Victoria Logs Baseline Test',
            'type' => AlertRuleType::VICTORIA_LOGS->value,
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
            'name' => 'Victoria Logs Reconcile Test',
            'type' => AlertRuleType::VICTORIA_LOGS->value,
            'userId' => $this->owner->id,
            'queryString' => 'status:failed',
            'countDocument' => 5,
            'minutes' => 5,
        ]);

        VictoriaLogsHistory::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => VictoriaLogsHistory::RESOLVED,
            'queryString' => 'status:failed',
            'countDocument' => 5,
            'currentCountDocument' => 0,
            'minutes' => 5,
            'createdAt' => Carbon::createFromTimestamp($this->fromTime - 86_400),
        ]);

        VictoriaLogsHistory::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => VictoriaLogsHistory::FIRE,
            'queryString' => 'status:failed',
            'countDocument' => 5,
            'currentCountDocument' => 8,
            'minutes' => 5,
            'createdAt' => Carbon::createFromTimestamp($this->fromTime + 50_000),
        ]);

        VictoriaLogsCheck::create([
            'alertRuleId' => $this->alertRule->_id,
            'queryString' => 'status:failed',
            'countDocument' => 5,
            'currentCountDocument' => 8,
            'minutes' => 5,
            'state' => VictoriaLogsCheck::FIRE,
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
