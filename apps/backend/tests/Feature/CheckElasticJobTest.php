<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Jobs\AddChecksJob;
use App\Jobs\CheckElasticJob;
use App\Models\AlertRule;
use App\Models\DataSource\DataSource;
use App\Models\ElasticCheck;
use App\Models\ElasticHistory;
use App\Services\DataSource\DataSourceAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TeamTestData;

describe('CheckElasticJob', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->dataSource = DataSource::withoutEvents(fn () => DataSource::create([
            'name' => 'Elastic Test Source',
            'type' => 'elastic',
            'url' => 'https://elastic.example.com',
            'username' => 'elastic-user',
            'password' => 'elastic-pass',
            'userId' => $this->owner->id,
        ]));

        $this->alertRule = AlertRule::create([
            'name' => 'Elastic Job Test',
            'type' => AlertRuleType::ELASTIC->value,
            'userId' => $this->owner->id,
            'dataSourceId' => $this->dataSource->_id,
            'dataviewTitle' => 'logs-*',
            'queryString' => 'level:error',
            'minutes' => 5,
            'countDocument' => 10,
            'conditionType' => ElasticCheck::CONDITION_TYPE_GREATER_OR_EQUAL,
        ]);
    });

    afterEach(function () {
        ElasticHistory::query()->where('alertRuleId', $this->alertRule->_id)->delete();
        ElasticCheck::query()->where('alertRuleId', $this->alertRule->_id)->delete();
        AlertRule::query()->where('_id', $this->alertRule->_id)->delete();
        DataSource::query()->where('_id', $this->dataSource->_id)->delete();
        TeamTestData::deleteUser($this->owner);
        Cache::flush();
    });

    it('does not write a false resolve when elasticsearch is unreachable while the alert is firing', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00', 'UTC'));

        ElasticCheck::create([
            'alertRuleId' => $this->alertRule->_id,
            'dataviewTitle' => 'logs-*',
            'queryString' => 'level:error',
            'minutes' => 5,
            'countDocument' => 10,
            'currentCountDocument' => 15,
            'state' => ElasticCheck::FIRE,
        ]);

        ElasticHistory::create([
            'alertRuleId' => $this->alertRule->_id,
            'state' => ElasticHistory::FIRE,
            'queryString' => 'level:error',
            'countDocument' => 10,
            'currentCountDocument' => 15,
            'minutes' => 5,
            'dataviewTitle' => 'logs-*',
        ]);

        Http::fake([
            'elastic.example.com/*' => Http::failedConnection(),
        ]);

        (new CheckElasticJob($this->alertRule))->handle();

        $check = ElasticCheck::query()->where('alertRuleId', $this->alertRule->_id)->first();
        $historyCount = ElasticHistory::query()->where('alertRuleId', $this->alertRule->_id)->count();

        expect((int) $check->state)->toBe(ElasticCheck::FIRE)
            ->and($historyCount)->toBe(1);
    });

    it('writes a fire history record when the threshold is exceeded', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00', 'UTC'));

        Http::fake([
            'elastic.example.com/*' => Http::response(['count' => 15], 200),
        ]);

        (new CheckElasticJob($this->alertRule))->handle();

        $check = ElasticCheck::query()->where('alertRuleId', $this->alertRule->_id)->first();
        $latestHistory = ElasticHistory::query()
            ->where('alertRuleId', $this->alertRule->_id)
            ->latest('createdAt')
            ->first();

        expect((int) $check->state)->toBe(ElasticCheck::FIRE)
            ->and($latestHistory)->not->toBeNull()
            ->and((int) $latestHistory->state)->toBe(ElasticHistory::FIRE);
    });

    it('does not queue a second dispatcher pass while the first lock is held', function () {
        Queue::fake();

        AddChecksJob::dispatch();
        AddChecksJob::dispatch();

        Queue::assertPushed(AddChecksJob::class, 1);
    });

    it('does not queue a second check for the same rule while the first lock is held', function () {
        Queue::fake();

        CheckElasticJob::dispatch($this->alertRule);
        CheckElasticJob::dispatch($this->alertRule);

        Queue::assertPushed(CheckElasticJob::class, 1);
    });

    it('does not dispatch a check while its data source is cooling down', function () {
        Queue::fake();

        app(DataSourceAvailability::class)->coolDown((string) $this->dataSource->_id);

        (new AddChecksJob)->handle();

        Queue::assertNotPushed(CheckElasticJob::class, function (CheckElasticJob $job) {
            return (string) $job->alert->_id === (string) $this->alertRule->_id;
        });
    });
});
