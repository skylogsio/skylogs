<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Enums\HealthAlertType;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\DataSource\DataSource;
use App\Models\HealthCheck;
use App\Models\HealthHistory;
use App\Models\Notify;
use App\Models\SkylogsInstance;
use App\Services\AlertRuleService;
use App\Services\Ha\HaReplicationContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TeamTestData;

describe('datasource health rules', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->ruleIds = [];
        $this->dataSourceIds = [];
        $this->instanceIds = [];
        $this->extraUsers = [];
        $this->autoCreatedBefore = null;
    });

    afterEach(function () {
        if (is_array($this->autoCreatedBefore)) {
            $newIds = AlertRule::query()
                ->where('autoCreated', true)
                ->pluck('_id')
                ->map(fn (mixed $id): string => (string) $id)
                ->reject(fn (string $id): bool => in_array($id, $this->autoCreatedBefore, true))
                ->all();

            $this->ruleIds = array_values(array_unique([...$this->ruleIds, ...$newIds]));
        }

        foreach ($this->ruleIds as $id) {
            $rule = AlertRule::query()->where('_id', $id)->first();

            if ($rule) {
                app(AlertRuleService::class)->delete($rule);
            }

            Notify::query()->where('alertRuleId', $id)->delete();
            HealthHistory::query()->where('alertRuleId', $id)->delete();
            HealthCheck::query()->where('alertRuleId', $id)->delete();
        }

        foreach ($this->dataSourceIds as $id) {
            $dataSource = DataSource::query()->where('_id', $id)->first();

            if ($dataSource) {
                $dataSource->delete();
            }
        }

        foreach ($this->instanceIds as $id) {
            SkylogsInstance::query()->where('_id', $id)->delete();
        }

        foreach ($this->extraUsers as $user) {
            TeamTestData::deleteUser($user);
        }

        TeamTestData::deleteUser($this->member);
        TeamTestData::deleteUser($this->owner);
        Cache::flush();
    });

    it('creates one datasource health rule when a datasource is created and does not notify', function () {
        Queue::fake();

        $service = app(AlertRuleService::class);
        $service->getAlerts(AlertRuleType::HEALTH);

        $name = 'Created DS '.uniqid();
        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/data-source', [
                'name' => $name,
                'type' => 'prometheus',
                'url' => 'https://created-'.uniqid().'.example.com',
            ])
            ->assertSuccessful()
            ->assertJson(['status' => true]);

        $dataSource = DataSource::query()->where('name', $name)->first();
        $this->dataSourceIds[] = $dataSource->_id;

        $rules = datasourceHealthRules($dataSource->_id);
        expect($rules)->toHaveCount(1);

        $rule = $rules->first();
        $this->ruleIds[] = $rule->_id;

        expect($rule->name)->toBe('Health: '.$name)
            ->and($rule->type)->toBe(AlertRuleType::HEALTH)
            ->and($rule->checkType)->toBe(HealthAlertType::DATASOURCE)
            ->and($rule->threshold)->toBe(3)
            ->and($rule->intervalSeconds)->toBe(30)
            ->and($rule->target)->toBe(['dataSourceId' => (string) $dataSource->_id])
            ->and($rule->autoCreated)->toBeTrue()
            ->and((string) $rule->userId)->toBe((string) $this->owner->id)
            ->and($rule->endpointIds ?? [])->toBe([])
            ->and($rule->userIds ?? [])->toBe([])
            ->and($rule->teamIds ?? [])->toBe([])
            ->and(HealthCheck::query()->where('alertRuleId', $rule->_id)->exists())->toBeFalse()
            ->and(Notify::query()->where('alertRuleId', $rule->_id)->count())->toBe(0);

        Queue::assertNotPushed(SendNotifyJob::class);

        $cached = $service->getAlerts(AlertRuleType::HEALTH);
        expect($cached->contains(fn (AlertRule $cachedRule): bool => (string) $cachedRule->_id === (string) $rule->_id))->toBeTrue();

        expect($service->createHealthDataSource($dataSource))->toBeNull()
            ->and(datasourceHealthRules($dataSource->_id))->toHaveCount(1);
    });

    it('uses the authenticated user instead of the datasource user id', function () {
        $this->actingAs($this->owner, 'api');

        $dataSource = DataSource::create([
            'name' => 'Owned DS '.uniqid(),
            'type' => 'prometheus',
            'url' => 'https://owned-'.uniqid().'.example.com',
            'userId' => $this->member->id,
        ]);
        $this->dataSourceIds[] = $dataSource->_id;

        $rule = datasourceHealthRules($dataSource->_id)->first();
        $this->ruleIds[] = $rule->_id;

        expect((string) $rule->userId)->toBe((string) $this->owner->id);
    });

    it('adds a short suffix when the health name is already taken', function () {
        $name = 'Suffix DS '.uniqid();
        $taken = AlertRule::query()->create([
            'name' => 'Health: '.$name,
            'type' => AlertRuleType::API,
            'userId' => $this->owner->id,
        ]);
        $alsoTaken = AlertRule::query()->create([
            'name' => 'Health: '.$name.' (2)',
            'type' => AlertRuleType::API,
            'userId' => $this->owner->id,
        ]);
        $this->ruleIds[] = $taken->_id;
        $this->ruleIds[] = $alsoTaken->_id;

        $this->actingAs($this->owner, 'api');
        $dataSource = DataSource::create([
            'name' => $name,
            'type' => 'grafana',
            'url' => 'https://suffix-'.uniqid().'.example.com',
        ]);
        $this->dataSourceIds[] = $dataSource->_id;

        $rule = datasourceHealthRules($dataSource->_id)->first();
        $this->ruleIds[] = $rule->_id;

        expect($rule->name)->toBe('Health: '.$name.' (3)');
    });

    it('does not rewrite the rule when the datasource changes', function () {
        $name = 'Update DS '.uniqid();
        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/data-source', [
                'name' => $name,
                'type' => 'prometheus',
                'url' => 'https://update-'.uniqid().'.example.com',
            ])
            ->assertSuccessful();

        $dataSource = DataSource::query()->where('name', $name)->first();
        $this->dataSourceIds[] = $dataSource->_id;
        $rule = datasourceHealthRules($dataSource->_id)->first();
        $this->ruleIds[] = $rule->_id;

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/data-source/'.$dataSource->_id, [
                'name' => $name.' renamed',
                'type' => 'grafana',
                'url' => 'https://updated-'.uniqid().'.example.com',
                'username' => 'changed-user',
                'password' => 'changed-pass',
            ])
            ->assertSuccessful()
            ->assertJson(['status' => true]);

        $rule->refresh();

        expect(datasourceHealthRules($dataSource->_id))->toHaveCount(1)
            ->and($rule->name)->toBe('Health: '.$name)
            ->and($rule->target)->toBe(['dataSourceId' => (string) $dataSource->_id])
            ->and($rule->threshold)->toBe(3)
            ->and($rule->intervalSeconds)->toBe(30);
    });

    it('deletes only the auto-created rule and its check history', function () {
        $service = app(AlertRuleService::class);
        $name = 'Delete DS '.uniqid();
        $this->actingAs($this->owner, 'api');

        $dataSource = DataSource::create([
            'name' => $name,
            'type' => 'prometheus',
            'url' => 'https://delete-'.uniqid().'.example.com',
        ]);
        $this->dataSourceIds[] = $dataSource->_id;

        $auto = datasourceHealthRules($dataSource->_id)->first();
        $this->ruleIds[] = $auto->_id;
        HealthCheck::query()->create([
            'alertRuleId' => $auto->_id,
            'state' => HealthCheck::DOWN,
            'counter' => 3,
        ]);
        HealthHistory::query()->create([
            'alertRuleId' => $auto->_id,
            'state' => HealthCheck::DOWN,
        ]);

        $hand = AlertRule::query()->create([
            'name' => 'Hand '.$name,
            'type' => AlertRuleType::HEALTH,
            'checkType' => HealthAlertType::DATASOURCE,
            'userId' => $this->owner->id,
            'threshold' => 4,
            'intervalSeconds' => 60,
            'target' => ['dataSourceId' => (string) $dataSource->_id],
            'endpointIds' => ['endpoint-1'],
            'userIds' => [(string) $this->member->id],
            'teamIds' => ['team-1'],
        ]);
        $this->ruleIds[] = $hand->_id;
        $handCheck = HealthCheck::query()->create([
            'alertRuleId' => $hand->_id,
            'state' => HealthCheck::DOWN,
            'counter' => 4,
        ]);
        HealthHistory::query()->create([
            'alertRuleId' => $hand->_id,
            'state' => HealthCheck::DOWN,
        ]);

        $http = AlertRule::query()->create([
            'name' => 'HTTP '.$name,
            'type' => AlertRuleType::HEALTH,
            'checkType' => HealthAlertType::HTTP,
            'userId' => $this->owner->id,
            'threshold' => 3,
            'intervalSeconds' => 30,
            'target' => [
                'url' => 'https://example.com/health',
                'method' => 'GET',
            ],
        ]);
        $this->ruleIds[] = $http->_id;

        $service->getAlerts(AlertRuleType::HEALTH);

        $this->actingAs($this->owner, 'api')
            ->deleteJson('/api/v1/data-source/'.$dataSource->_id)
            ->assertSuccessful();

        expect(AlertRule::query()->where('_id', $auto->_id)->exists())->toBeFalse()
            ->and(HealthCheck::query()->where('alertRuleId', $auto->_id)->exists())->toBeFalse()
            ->and(HealthHistory::query()->where('alertRuleId', $auto->_id)->exists())->toBeFalse()
            ->and(AlertRule::query()->where('_id', $hand->_id)->exists())->toBeTrue()
            ->and(HealthCheck::query()->where('_id', $handCheck->_id)->exists())->toBeTrue()
            ->and(HealthHistory::query()->where('alertRuleId', $hand->_id)->exists())->toBeTrue()
            ->and((bool) $hand->fresh()->autoCreated)->toBeFalse()
            ->and(AlertRule::query()->where('_id', $http->_id)->exists())->toBeTrue();

        $cached = $service->getAlerts(AlertRuleType::HEALTH);
        expect($cached->contains(fn (AlertRule $cachedRule): bool => (string) $cachedRule->_id === (string) $auto->_id))->toBeFalse()
            ->and($cached->contains(fn (AlertRule $cachedRule): bool => (string) $cachedRule->_id === (string) $hand->_id))->toBeTrue();
    });

    it('backfills a missing rule without replacing one a user already created', function () {
        $missing = DataSource::withoutEvents(fn () => DataSource::create([
            'name' => 'Backfill missing '.uniqid(),
            'type' => 'prometheus',
            'url' => 'https://backfill-missing-'.uniqid().'.example.com',
            'userId' => $this->member->id,
        ]));
        $this->dataSourceIds[] = $missing->_id;

        $covered = DataSource::withoutEvents(fn () => DataSource::create([
            'name' => 'Backfill covered '.uniqid(),
            'type' => 'elastic',
            'url' => 'https://backfill-covered-'.uniqid().'.example.com',
            'userId' => $this->owner->id,
        ]));
        $this->dataSourceIds[] = $covered->_id;

        $hand = AlertRule::query()->create([
            'name' => 'Hand backfill '.uniqid(),
            'type' => AlertRuleType::HEALTH,
            'checkType' => HealthAlertType::DATASOURCE,
            'userId' => $this->owner->id,
            'threshold' => 5,
            'intervalSeconds' => 45,
            'target' => ['dataSourceId' => (string) $covered->_id],
        ]);
        $this->ruleIds[] = $hand->_id;

        $this->actingAs($this->owner, 'api');

        $created = app(AlertRuleService::class)->createHealthDataSource($missing, backfill: true);
        $this->ruleIds[] = $created->_id;

        expect($created->autoCreated)->toBeTrue()
            ->and((string) $created->userId)->toBe((string) $this->member->id)
            ->and($created->endpointIds ?? [])->toBe([])
            ->and(app(AlertRuleService::class)->createHealthDataSource($covered, backfill: true))->toBeNull()
            ->and(datasourceHealthRules($covered->_id))->toHaveCount(1)
            ->and((bool) $hand->fresh()->autoCreated)->toBeFalse()
            ->and($hand->fresh()->threshold)->toBe(5);
    });

    it('uses the oldest admin when a backfill datasource has no user', function () {
        $admin = TeamTestData::createUser(Constants::ROLE_OWNER);
        $olderMember = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->extraUsers = [$admin, $olderMember];
        $admin->createdAt = Carbon::create(1971, 1, 1);
        $admin->save();
        $olderMember->createdAt = Carbon::create(1970, 1, 1);
        $olderMember->save();

        $dataSource = DataSource::withoutEvents(fn () => DataSource::create([
            'name' => 'No owner DS '.uniqid(),
            'type' => 'prometheus',
            'url' => 'https://no-owner-'.uniqid().'.example.com',
        ]));
        $this->dataSourceIds[] = $dataSource->_id;

        $rule = app(AlertRuleService::class)->createHealthDataSource($dataSource, backfill: true);
        $this->ruleIds[] = $rule->_id;

        expect((string) $rule->userId)->toBe((string) $admin->fresh()->id)
            ->and($rule->userId)->not->toBeNull();
    });

    it('backfills existing datasources once', function () {
        $this->autoCreatedBefore = AlertRule::query()
            ->where('autoCreated', true)
            ->pluck('_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        $missing = DataSource::withoutEvents(fn () => DataSource::create([
            'name' => 'Command missing '.uniqid(),
            'type' => 'zabbix',
            'url' => 'https://command-missing-'.uniqid().'.example.com',
            'userId' => $this->owner->id,
        ]));
        $this->dataSourceIds[] = $missing->_id;

        $this->artisan('health:backfill-datasource-rules')->assertSuccessful();

        $created = datasourceHealthRules($missing->_id);
        expect($created)->toHaveCount(1)
            ->and($created->first()->autoCreated)->toBeTrue()
            ->and((string) $created->first()->userId)->toBe((string) $this->owner->id);

        $this->artisan('health:backfill-datasource-rules')->assertSuccessful();

        expect(datasourceHealthRules($missing->_id))->toHaveCount(1);
    });

    it('does not provision a health rule while replicated configuration is being applied', function () {
        $dataSource = HaReplicationContext::apply(fn () => DataSource::create([
            'name' => 'Replicated DS '.uniqid(),
            'type' => 'prometheus',
            'url' => 'https://replicated-'.uniqid().'.example.com',
            'userId' => $this->owner->id,
        ]));
        $this->dataSourceIds[] = $dataSource->_id;

        expect(datasourceHealthRules($dataSource->_id))->toHaveCount(0);

        $kept = DataSource::create([
            'name' => 'Kept DS '.uniqid(),
            'type' => 'prometheus',
            'url' => 'https://kept-'.uniqid().'.example.com',
            'userId' => $this->owner->id,
        ]);
        $this->dataSourceIds[] = $kept->_id;
        $rule = datasourceHealthRules($kept->_id)->first();
        $this->ruleIds[] = $rule->_id;

        HaReplicationContext::apply(function () use ($kept): void {
            $kept->delete();
        });

        expect(AlertRule::query()->where('_id', $rule->_id)->exists())->toBeTrue();
    });

    it('does not create a health rule for a cluster agent', function () {
        $before = AlertRule::query()->where('type', AlertRuleType::HEALTH)->count();

        $response = $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/skylogs-instance', [
                'name' => 'Agent '.uniqid(),
                'type' => 'agent',
                'url' => 'https://agent-'.uniqid().'.example.com',
            ])
            ->assertSuccessful()
            ->assertJson(['status' => true]);

        $instanceId = $response->json('data.id') ?? $response->json('data._id');
        $this->instanceIds[] = $instanceId;

        expect(AlertRule::query()->where('type', AlertRuleType::HEALTH)->count())->toBe($before);
    });
});

/**
 * @return Collection<int, AlertRule>
 */
function datasourceHealthRules(mixed $dataSourceId): Collection
{
    return AlertRule::query()
        ->where('type', AlertRuleType::HEALTH)
        ->where('checkType', HealthAlertType::DATASOURCE)
        ->where('target.dataSourceId', (string) $dataSourceId)
        ->get();
}
