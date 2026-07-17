<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Jobs\CheckVictoriaLogsJob;
use App\Models\AlertRule;
use App\Models\DataSource\DataSource;
use App\Models\VictoriaLogsCheck;
use App\Models\VictoriaLogsHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\TeamTestData;

describe('CheckVictoriaLogsJob', function () {
    beforeEach(function () {
        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->dataSource = DataSource::create([
            'name' => 'Victoria Logs Test Source',
            'type' => 'victoria_logs',
            'url' => 'https://victoria.example.com',
            'userId' => $this->owner->id,
        ]);

        $this->alertRule = AlertRule::create([
            'name' => 'Victoria Logs Job Test',
            'type' => AlertRuleType::VICTORIA_LOGS->value,
            'userId' => $this->owner->id,
            'dataSourceId' => $this->dataSource->_id,
            'queryString' => 'status:failed',
            'minutes' => 5,
            'countDocument' => 5,
            'conditionType' => VictoriaLogsCheck::CONDITION_TYPE_GREATER_OR_EQUAL,
        ]);
    });

    afterEach(function () {
        VictoriaLogsHistory::query()->where('alertRuleId', $this->alertRule->_id)->delete();
        VictoriaLogsCheck::query()->where('alertRuleId', $this->alertRule->_id)->delete();
        AlertRule::query()->where('_id', $this->alertRule->_id)->delete();
        DataSource::query()->where('_id', $this->dataSource->_id)->delete();
        TeamTestData::deleteUser($this->owner);
    });

    it('does not write a false resolve when victoria logs is unreachable while the alert is firing', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00', 'UTC'));

        VictoriaLogsCheck::create([
            'alertRuleId' => $this->alertRule->_id,
            'queryString' => 'status:failed',
            'minutes' => 5,
            'countDocument' => 5,
            'currentCountDocument' => 8,
            'state' => VictoriaLogsCheck::FIRE,
        ]);

        VictoriaLogsHistory::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => VictoriaLogsHistory::FIRE,
            'queryString' => 'status:failed',
            'countDocument' => 5,
            'currentCountDocument' => 8,
            'minutes' => 5,
        ]);

        Http::fake([
            'victoria.example.com/*' => Http::failedConnection(),
        ]);

        (new CheckVictoriaLogsJob($this->alertRule))->handle();

        $check = VictoriaLogsCheck::query()->where('alertRuleId', $this->alertRule->_id)->first();
        $historyCount = VictoriaLogsHistory::query()->where('alertRuleId', $this->alertRule->_id)->count();

        expect((int) $check->state)->toBe(VictoriaLogsCheck::FIRE)
            ->and($historyCount)->toBe(1);
    });

    it('writes a fire history record when the threshold is exceeded', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00', 'UTC'));

        Http::fake([
            'victoria.example.com/*' => Http::response(['total' => 8], 200),
        ]);

        (new CheckVictoriaLogsJob($this->alertRule))->handle();

        $check = VictoriaLogsCheck::query()->where('alertRuleId', $this->alertRule->_id)->first();
        $latestHistory = VictoriaLogsHistory::query()
            ->where('alertRuleId', $this->alertRule->_id)
            ->latest('createdAt')
            ->first();

        expect((int) $check->state)->toBe(VictoriaLogsCheck::FIRE)
            ->and($latestHistory)->not->toBeNull()
            ->and((int) $latestHistory->state)->toBe(VictoriaLogsHistory::FIRE);
    });
});
