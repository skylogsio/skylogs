<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Enums\HealthAlertType;
use App\Jobs\RunHealthChecksJob;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\DataSource\DataSource;
use App\Models\HealthCheck;
use App\Models\HealthHistory;
use App\Models\Notify;
use App\Services\AlertRuleService;
use App\Services\Health\HealthCheckRunner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TeamTestData;

describe('health alert flow', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();
        Queue::fake();

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->ruleIds = [];
        $this->dataSourceIds = [];
    });

    afterEach(function () {
        Carbon::setTestNow();

        foreach ($this->ruleIds as $id) {
            Notify::query()->where('alertRuleId', $id)->delete();
            HealthHistory::query()->where('alertRuleId', $id)->delete();
            HealthCheck::query()->where('alertRuleId', $id)->delete();
            AlertRule::query()->where('_id', $id)->delete();
        }

        foreach ($this->dataSourceIds as $id) {
            DataSource::query()->where('_id', $id)->delete();
        }

        TeamTestData::deleteUser($this->owner);
        Cache::flush();
    });

    it('creates a health rule for each creatable target and rejects a bad one', function () {
        $dataSource = DataSource::withoutEvents(fn () => DataSource::create([
            'name' => 'Health DS '.uniqid(),
            'type' => 'prometheus',
            'url' => 'https://prom.example.com',
        ]));
        $this->dataSourceIds[] = $dataSource->_id;

        $httpName = 'Health HTTP '.uniqid();
        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule', healthPayload($httpName, [
                'checkType' => 'http',
                'target' => [
                    'url' => 'https://example.com/health',
                    'method' => 'GET',
                ],
            ]))
            ->assertSuccessful()
            ->assertJson(['status' => true]);

        $datasourceName = 'Health Datasource '.uniqid();
        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule', healthPayload($datasourceName, [
                'checkType' => 'datasource',
                'target' => ['dataSourceId' => (string) $dataSource->_id],
            ]))
            ->assertSuccessful()
            ->assertJson(['status' => true]);

        $rules = AlertRule::query()->whereIn('name', [$httpName, $datasourceName])->get();
        $this->ruleIds = $rules->pluck('_id')->all();

        expect($rules)->toHaveCount(2)
            ->and($rules->firstWhere('name', $httpName)->threshold)->toBe(3)
            ->and($rules->firstWhere('name', $httpName)->intervalSeconds)->toBe(30)
            ->and($rules->firstWhere('name', $datasourceName)->checkType)->toBe(HealthAlertType::DATASOURCE);

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule', healthPayload('Health Bad '.uniqid(), [
                'checkType' => 'http',
                'target' => [
                    'url' => 'ftp://example.com/health',
                    'method' => 'GET',
                ],
            ]))
            ->assertSuccessful()
            ->assertJson(['status' => false]);

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule', healthPayload('Health Missing '.uniqid(), [
                'checkType' => 'datasource',
                'target' => [],
            ]))
            ->assertSuccessful()
            ->assertJson(['status' => false]);

        expect(AlertRule::query()->where('name', 'like', 'Health Bad%')->count())->toBe(0)
            ->and(AlertRule::query()->where('name', 'like', 'Health Missing%')->count())->toBe(0);
    });

    it('clears the check when the target or threshold changes and keeps it when renaming', function () {
        $name = 'Health Rename '.uniqid();
        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule', healthPayload($name, [
                'checkType' => 'http',
                'threshold' => 3,
                'target' => [
                    'url' => 'https://example.com/one',
                    'method' => 'GET',
                ],
            ]))
            ->assertSuccessful();

        $rule = AlertRule::query()->where('name', $name)->first();
        $this->ruleIds[] = $rule->_id;

        $check = HealthCheck::create([
            'alertRuleId' => $rule->_id,
            'state' => HealthCheck::UP,
            'counter' => 2,
        ]);

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/alert-rule/'.$rule->_id, healthPayload($name.' renamed', [
                'checkType' => 'http',
                'threshold' => 3,
                'intervalSeconds' => 30,
                'target' => [
                    'url' => 'https://example.com/one',
                    'method' => 'GET',
                ],
            ]))
            ->assertSuccessful()
            ->assertJson(['status' => true]);

        expect(HealthCheck::query()->where('_id', $check->_id)->exists())->toBeTrue();

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/alert-rule/'.$rule->_id, healthPayload($name.' renamed', [
                'checkType' => 'http',
                'threshold' => 4,
                'intervalSeconds' => 30,
                'target' => [
                    'url' => 'https://example.com/one',
                    'method' => 'GET',
                ],
            ]))
            ->assertSuccessful();

        expect(HealthCheck::query()->where('alertRuleId', $rule->_id)->exists())->toBeFalse();

        HealthCheck::create([
            'alertRuleId' => $rule->_id,
            'state' => HealthCheck::DOWN,
            'counter' => 4,
        ]);

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/alert-rule/'.$rule->_id, healthPayload($name.' renamed', [
                'checkType' => 'http',
                'threshold' => 4,
                'intervalSeconds' => 30,
                'target' => [
                    'url' => 'https://example.com/two',
                    'method' => 'GET',
                ],
            ]))
            ->assertSuccessful();

        expect(HealthCheck::query()->where('alertRuleId', $rule->_id)->exists())->toBeFalse();
    });

    it('probes only rules whose interval has elapsed', function () {
        Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00'));
        Http::preventStrayRequests();
        Http::fake([
            'https://due.example.com/*' => Http::response('ok', 200),
            'https://waiting.example.com/*' => Http::response('ok', 200),
        ]);

        $due = healthRule($this->owner->id, 'https://due.example.com/health');
        $waiting = healthRule($this->owner->id, 'https://waiting.example.com/health', [
            'intervalSeconds' => 3600,
        ]);
        $this->ruleIds = [$due->_id, $waiting->_id];

        HealthCheck::create([
            'alertRuleId' => $waiting->_id,
            'state' => HealthCheck::UP,
            'counter' => 0,
            'lastCheckedAt' => now()->getTimestamp(),
        ]);

        (new RunHealthChecksJob)->handle(app(HealthCheckRunner::class));

        Http::assertSent(fn ($request) => $request->url() === 'https://due.example.com/health');
        Http::assertNotSent(fn ($request) => $request->url() === 'https://waiting.example.com/health');
    });

    it('notifies once when a check crosses down and once when it recovers', function () {
        Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00'));
        Http::preventStrayRequests();

        $rule = healthRule($this->owner->id, 'https://flip.example.com/health', [
            'threshold' => 2,
            'intervalSeconds' => 10,
        ]);
        $this->ruleIds[] = $rule->_id;
        $rule->acknowledgedBy = $this->owner->id;
        $rule->save();

        Http::fake([
            'https://flip.example.com/*' => Http::sequence()
                ->push('ok', 200)
                ->push('no', 500)
                ->push('no', 500)
                ->push('no', 500)
                ->push('ok', 200)
                ->push('ok', 200),
        ]);

        runHealthChecks();

        $check = HealthCheck::query()->where('alertRuleId', $rule->_id)->first();
        expect((int) $check->state)->toBe(HealthCheck::UP)
            ->and(HealthHistory::query()->where('alertRuleId', $rule->_id)->count())->toBe(0)
            ->and(healthNotifyCount($rule->_id))->toBe(0);

        travelForHealth(10);
        runHealthChecks();

        $check = HealthCheck::query()->where('alertRuleId', $rule->_id)->first();
        expect((int) $check->state)->toBe(HealthCheck::UP)
            ->and((int) $check->counter)->toBe(1)
            ->and(HealthHistory::query()->where('alertRuleId', $rule->_id)->count())->toBe(0);

        travelForHealth(10);
        runHealthChecks();

        $check = HealthCheck::query()->where('alertRuleId', $rule->_id)->first();
        $rule->refresh();
        expect((int) $check->state)->toBe(HealthCheck::DOWN)
            ->and($check->lastError)->toBe('HTTP 500')
            ->and($rule->state)->toBe(AlertRule::CRITICAL)
            ->and(HealthHistory::query()->where('alertRuleId', $rule->_id)->count())->toBe(1)
            ->and(healthNotifyCount($rule->_id))->toBe(1);

        travelForHealth(10);
        runHealthChecks();

        expect(HealthHistory::query()->where('alertRuleId', $rule->_id)->count())->toBe(1)
            ->and(healthNotifyCount($rule->_id))->toBe(1);

        travelForHealth(10);
        runHealthChecks();

        $check = HealthCheck::query()->where('alertRuleId', $rule->_id)->first();
        expect((int) $check->state)->toBe(HealthCheck::DOWN)
            ->and((int) $check->successCounter)->toBe(1)
            ->and(healthNotifyCount($rule->_id))->toBe(1);

        travelForHealth(10);
        runHealthChecks();

        $check = HealthCheck::query()->where('alertRuleId', $rule->_id)->first();
        $rule->refresh();
        expect((int) $check->state)->toBe(HealthCheck::UP)
            ->and($check->lastError)->toBeNull()
            ->and($rule->state)->toBe(AlertRule::RESOlVED)
            ->and($rule->acknowledgedBy)->toBeNull()
            ->and(HealthHistory::query()->where('alertRuleId', $rule->_id)->count())->toBe(2)
            ->and(healthNotifyCount($rule->_id))->toBe(2);
    });

    it('counts a missing datasource as a failure without calling http', function () {
        Http::preventStrayRequests();
        Http::fake();

        $missingDatasource = healthRule($this->owner->id, 'https://unused.example.com', [
            'checkType' => HealthAlertType::DATASOURCE,
            'threshold' => 1,
            'target' => ['dataSourceId' => '6512ab000000000000000099'],
        ]);
        $this->ruleIds = [$missingDatasource->_id];

        runHealthChecks();

        Http::assertNothingSent();

        $datasourceCheck = HealthCheck::query()->where('alertRuleId', $missingDatasource->_id)->first();

        expect($datasourceCheck->lastError)->toBe('target missing')
            ->and((int) $datasourceCheck->state)->toBe(HealthCheck::DOWN);
    });

    it('keeps the check when the rule state is saved and removes it when the rule is deleted', function () {
        $rule = healthRule($this->owner->id, 'https://keep.example.com/health');
        $check = HealthCheck::create([
            'alertRuleId' => $rule->_id,
            'state' => HealthCheck::DOWN,
            'counter' => 3,
            'url' => 'https://keep.example.com/health',
        ]);
        HealthHistory::create([
            'alertRuleId' => $rule->_id,
            'state' => HealthCheck::DOWN,
            'url' => 'https://keep.example.com/health',
        ]);

        $rule->state = AlertRule::CRITICAL;
        $rule->save();

        expect(HealthCheck::query()->where('_id', $check->_id)->exists())->toBeTrue();

        $id = $rule->_id;
        app(AlertRuleService::class)->delete($rule);

        expect(HealthCheck::query()->where('alertRuleId', $id)->exists())->toBeFalse()
            ->and(HealthHistory::query()->where('alertRuleId', $id)->exists())->toBeFalse()
            ->and(AlertRule::query()->where('_id', $id)->exists())->toBeFalse();
    });
});

function healthPayload(string $name, array $overrides): array
{
    return [
        'name' => $name,
        'type' => AlertRuleType::HEALTH->value,
        'threshold' => 3,
        'intervalSeconds' => 30,
        ...$overrides,
    ];
}

function healthRule(string $userId, string $url, array $overrides = []): AlertRule
{
    return AlertRule::create([
        'name' => 'Health rule '.uniqid(),
        'type' => AlertRuleType::HEALTH,
        'userId' => $userId,
        'checkType' => HealthAlertType::HTTP,
        'threshold' => 3,
        'intervalSeconds' => 30,
        'target' => [
            'url' => $url,
            'method' => 'GET',
            'headers' => [],
            'body' => null,
            'expectedStatuses' => [],
            'bodyContains' => null,
            'timeoutSeconds' => 5,
            'verifyTls' => true,
        ],
        ...$overrides,
    ]);
}

function runHealthChecks(): void
{
    (new RunHealthChecksJob)->handle(app(HealthCheckRunner::class));
}

function travelForHealth(int $seconds): void
{
    $current = Carbon::getTestNow() ?? now();

    Carbon::setTestNow($current->copy()->addSeconds($seconds));
}

function healthNotifyCount(mixed $alertRuleId): int
{
    return Notify::query()
        ->where('alertRuleId', $alertRuleId)
        ->where('type', SendNotifyJob::HEALTH_CHECK)
        ->count();
}
