<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertRule;
use App\Models\Notify;
use App\Models\PrometheusCheck;
use App\Models\PrometheusHistory;
use App\Services\PrometheusService;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TeamTestData;

describe('Prometheus check state alignment', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Queue::fake();

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->alertRule = null;
    });

    afterEach(function () {
        if ($this->alertRule !== null) {
            $alertRuleId = $this->alertRule->_id;
            Notify::query()->where('alertRuleId', $alertRuleId)->delete();
            PrometheusHistory::query()->where('alertRuleId', $alertRuleId)->delete();
            PrometheusCheck::query()->where('alertRuleId', $alertRuleId)->delete();
            AlertRule::query()->where('_id', $alertRuleId)->delete();
        }

        TeamTestData::deleteUser($this->owner);
    });

    it('aligns a null rule to critical when the firing alerts are unchanged', function () {
        $labels = ['alertname' => 'HighCPU', 'instance' => 'a'];
        $this->alertRule = AlertRule::create([
            'name' => 'Align firing '.uniqid(),
            'type' => AlertRuleType::PROMETHEUS->value,
            'userId' => $this->owner->id,
        ]);

        PrometheusCheck::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => PrometheusCheck::FIRE,
            'alerts' => [[
                'labels' => $labels,
                'annotations' => [],
                'skylogsStatus' => PrometheusCheck::FIRE,
            ]],
        ]);

        app(PrometheusService::class)->CheckAlerts(
            collect([$this->alertRule]),
            [
                (string) $this->alertRule->getKey() => [[
                    'labels' => $labels,
                    'annotations' => [],
                    'dataSourceId' => 'ds-1',
                ]],
            ],
        );

        $this->alertRule->refresh();
        $check = PrometheusCheck::where('alertRuleId', $this->alertRule->_id)->first();

        expect($this->alertRule->state)->toBe(AlertRule::CRITICAL)
            ->and((int) $this->alertRule->fireCount)->toBe(1)
            ->and((int) $check->state)->toBe(PrometheusCheck::FIRE)
            ->and(PrometheusHistory::where('alertRuleId', $this->alertRule->_id)->count())->toBe(0)
            ->and(Notify::where('alertRuleId', $this->alertRule->_id)->count())->toBe(0);
    });

    it('aligns a null rule to resolved when nothing is firing', function () {
        $this->alertRule = AlertRule::create([
            'name' => 'Align resolved '.uniqid(),
            'type' => AlertRuleType::PROMETHEUS->value,
            'userId' => $this->owner->id,
        ]);

        PrometheusCheck::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => PrometheusCheck::RESOLVED,
            'alerts' => [],
        ]);

        app(PrometheusService::class)->CheckAlerts(collect([$this->alertRule]), []);

        $this->alertRule->refresh();
        $check = PrometheusCheck::where('alertRuleId', $this->alertRule->_id)->first();

        expect($this->alertRule->state)->toBe(AlertRule::RESOlVED)
            ->and((int) $this->alertRule->fireCount)->toBe(0)
            ->and((int) $check->state)->toBe(PrometheusCheck::RESOLVED)
            ->and(PrometheusHistory::where('alertRuleId', $this->alertRule->_id)->count())->toBe(0)
            ->and(Notify::where('alertRuleId', $this->alertRule->_id)->count())->toBe(0);
    });

    it('still records history and notifies when a new alert fires', function () {
        $this->alertRule = AlertRule::create([
            'name' => 'Align transition '.uniqid(),
            'type' => AlertRuleType::PROMETHEUS->value,
            'userId' => $this->owner->id,
            'state' => AlertRule::RESOlVED,
            'fireCount' => 0,
        ]);

        PrometheusCheck::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => PrometheusCheck::RESOLVED,
            'alerts' => [],
        ]);

        app(PrometheusService::class)->CheckAlerts(
            collect([$this->alertRule]),
            [
                (string) $this->alertRule->getKey() => [[
                    'labels' => ['alertname' => 'DiskFull', 'instance' => 'b'],
                    'annotations' => [],
                    'dataSourceId' => 'ds-1',
                ]],
            ],
        );

        $this->alertRule->refresh();
        $check = PrometheusCheck::where('alertRuleId', $this->alertRule->_id)->first();

        expect($this->alertRule->state)->toBe(AlertRule::CRITICAL)
            ->and((int) $this->alertRule->fireCount)->toBe(1)
            ->and((int) $check->state)->toBe(PrometheusCheck::FIRE)
            ->and(PrometheusHistory::where('alertRuleId', $this->alertRule->_id)->count())->toBe(1)
            ->and(Notify::where('alertRuleId', $this->alertRule->_id)->count())->toBe(1);
    });
});
