<?php

use App\Enums\Constants;
use App\Models\Config\ConfigCall;
use App\Models\Config\ConfigSms;
use App\Models\Config\ConfigTelegram;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\TeamTestData;

/**
 * @param  class-string<Model>  $model
 * @param  array<string, mixed>  $empty
 * @return list<array<string, mixed>>
 */
function snapshotNotificationConfigs(string $model, array $empty): array
{
    return $model::query()->get()->map(function (Model $config) use ($empty) {
        $saved = ['id' => (string) $config->getKey()];
        $clear = [];

        foreach ($empty as $field => $value) {
            $saved[$field] = $config->getAttribute($field);
            $clear[$field] = $value;
        }

        $config->update($clear);

        return $saved;
    })->all();
}

/**
 * @param  class-string<Model>  $model
 * @param  list<array<string, mixed>>  $snapshot
 */
function restoreNotificationConfigs(string $model, array $snapshot): void
{
    foreach ($snapshot as $row) {
        $id = $row['id'];
        unset($row['id']);
        $model::query()->where('_id', $id)->update($row);
    }
}

describe('config priorities', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);

        $this->smsSnapshot = snapshotNotificationConfigs(ConfigSms::class, [
            'priority' => null,
            'isDefault' => false,
            'isBackUp' => false,
        ]);
        $this->callSnapshot = snapshotNotificationConfigs(ConfigCall::class, [
            'priority' => null,
            'isDefault' => false,
            'isBackUp' => false,
        ]);
        $this->telegramSnapshot = snapshotNotificationConfigs(ConfigTelegram::class, [
            'priority' => null,
            'active' => false,
        ]);
    });

    afterEach(function () {
        ConfigSms::query()->where('name', 'like', 'failover-test-%')->delete();
        ConfigCall::query()->where('name', 'like', 'failover-test-%')->delete();
        ConfigTelegram::query()->where('name', 'like', 'failover-test-%')->delete();

        restoreNotificationConfigs(ConfigSms::class, $this->smsSnapshot);
        restoreNotificationConfigs(ConfigCall::class, $this->callSnapshot);
        restoreNotificationConfigs(ConfigTelegram::class, $this->telegramSnapshot);

        TeamTestData::deleteUser($this->owner);
        TeamTestData::deleteUser($this->member);
    });

    it('orders sms profiles and keeps the default and backup flags in sync', function () {
        $first = ConfigSms::create([
            'name' => 'failover-test-sms-a',
            'provider' => 'kaveNegar',
            'apiToken' => 'a',
            'senderNumber' => '1000',
            'isDefault' => false,
            'isBackUp' => false,
            'priority' => null,
        ]);
        $second = ConfigSms::create([
            'name' => 'failover-test-sms-b',
            'provider' => 'kaveNegar',
            'apiToken' => 'b',
            'senderNumber' => '2000',
            'isDefault' => false,
            'isBackUp' => false,
            'priority' => null,
        ]);
        $third = ConfigSms::create([
            'name' => 'failover-test-sms-c',
            'provider' => 'kaveNegar',
            'apiToken' => 'c',
            'senderNumber' => '3000',
            'isDefault' => false,
            'isBackUp' => false,
            'priority' => null,
        ]);

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/config/sms/priorities', [
                'ids' => [(string) $third->id, (string) $first->id, (string) $second->id],
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $third->refresh();
        $first->refresh();
        $second->refresh();

        expect((int) $third->priority)->toBe(1)
            ->and((bool) $third->isDefault)->toBeTrue()
            ->and((bool) $third->isBackUp)->toBeFalse()
            ->and((int) $first->priority)->toBe(2)
            ->and((bool) $first->isBackUp)->toBeTrue()
            ->and((int) $second->priority)->toBe(3)
            ->and((bool) $second->isDefault)->toBeFalse()
            ->and((bool) $second->isBackUp)->toBeFalse();
    });

    it('rejects unknown and repeated sms profile ids', function () {
        $config = ConfigSms::create([
            'name' => 'failover-test-sms-known',
            'provider' => 'kaveNegar',
            'apiToken' => 'a',
            'senderNumber' => '1000',
            'isDefault' => false,
            'isBackUp' => false,
            'priority' => null,
        ]);

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/config/sms/priorities', ['ids' => ['aaaaaaaaaaaaaaaaaaaaaaaa']])
            ->assertUnprocessable();

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/config/sms/priorities', ['ids' => [(string) $config->id, (string) $config->id]])
            ->assertUnprocessable();
    });

    it('keeps the sms backup when another config is made default', function () {
        $default = ConfigSms::create([
            'name' => 'failover-test-sms-default',
            'provider' => 'kaveNegar',
            'apiToken' => 'a',
            'senderNumber' => '1000',
            'isDefault' => true,
            'isBackUp' => false,
            'priority' => 1,
        ]);
        $backup = ConfigSms::create([
            'name' => 'failover-test-sms-backup',
            'provider' => 'kaveNegar',
            'apiToken' => 'b',
            'senderNumber' => '2000',
            'isDefault' => false,
            'isBackUp' => true,
            'priority' => 2,
        ]);
        $next = ConfigSms::create([
            'name' => 'failover-test-sms-next',
            'provider' => 'kaveNegar',
            'apiToken' => 'c',
            'senderNumber' => '3000',
            'isDefault' => false,
            'isBackUp' => false,
            'priority' => null,
        ]);

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/config/sms/make-default/'.$next->id)
            ->assertSuccessful();

        $next->refresh();
        $backup->refresh();
        $default->refresh();

        expect((int) $next->priority)->toBe(1)
            ->and((bool) $next->isDefault)->toBeTrue()
            ->and((int) $backup->priority)->toBe(2)
            ->and((bool) $backup->isBackUp)->toBeTrue()
            ->and($default->priority)->toBeNull()
            ->and((bool) $default->isDefault)->toBeFalse();
    });

    it('rejects making the sms default the backup', function () {
        $default = ConfigSms::create([
            'name' => 'failover-test-sms-only-default',
            'provider' => 'kaveNegar',
            'apiToken' => 'a',
            'senderNumber' => '1000',
            'isDefault' => true,
            'isBackUp' => false,
            'priority' => 1,
        ]);

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/config/sms/make-backup/'.$default->id)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This Config SMS is default');
    });

    it('forbids a member from changing sms priorities', function () {
        $this->actingAs($this->member, 'api')
            ->putJson('/api/v1/config/sms/priorities', ['ids' => []])
            ->assertForbidden();
    });

    it('orders call profiles the same way', function () {
        $first = ConfigCall::create([
            'name' => 'failover-test-call-a',
            'provider' => 'kaveNegar',
            'apiToken' => 'a',
            'isDefault' => false,
            'isBackUp' => false,
            'priority' => null,
        ]);
        $second = ConfigCall::create([
            'name' => 'failover-test-call-b',
            'provider' => 'kaveNegar',
            'apiToken' => 'b',
            'isDefault' => false,
            'isBackUp' => false,
            'priority' => null,
        ]);

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/config/call/priorities', [
                'ids' => [(string) $second->id, (string) $first->id],
            ])
            ->assertSuccessful();

        $second->refresh();
        $first->refresh();

        expect((int) $second->priority)->toBe(1)
            ->and((bool) $second->isDefault)->toBeTrue()
            ->and((int) $first->priority)->toBe(2)
            ->and((bool) $first->isBackUp)->toBeTrue();
    });

    it('keeps an edited telegram proxy in the chain', function () {
        $proxy = ConfigTelegram::create([
            'name' => 'failover-test-proxy',
            'type' => 'socks5',
            'host' => 'proxy.local',
            'port' => '1080',
            'username' => '',
            'password' => '',
            'active' => true,
            'priority' => 1,
        ]);

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/config/telegram/'.$proxy->id, [
                'name' => 'failover-test-proxy-renamed',
                'type' => 'socks5',
                'host' => 'proxy.local',
                'port' => '1080',
            ])
            ->assertSuccessful();

        $proxy->refresh();

        expect($proxy->name)->toBe('failover-test-proxy-renamed')
            ->and((bool) $proxy->active)->toBeTrue()
            ->and((int) $proxy->priority)->toBe(1);
    });

    it('activates one telegram transport and can clear the chain', function () {
        $first = ConfigTelegram::create([
            'name' => 'failover-test-tg-a',
            'type' => 'http',
            'host' => 'one.local',
            'port' => '8080',
            'username' => '',
            'password' => '',
            'active' => false,
            'priority' => null,
        ]);
        $second = ConfigTelegram::create([
            'name' => 'failover-test-tg-b',
            'type' => 'socks5',
            'host' => 'two.local',
            'port' => '1080',
            'username' => '',
            'password' => '',
            'active' => false,
            'priority' => null,
        ]);

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/config/telegram/priorities', [
                'ids' => [(string) $second->id, (string) $first->id],
            ])
            ->assertSuccessful();

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/config/telegram/activate/'.$first->id)
            ->assertSuccessful();

        $first->refresh();
        $second->refresh();

        expect((bool) $first->active)->toBeTrue()
            ->and((int) $first->priority)->toBe(1)
            ->and((bool) $second->active)->toBeFalse()
            ->and($second->priority)->toBeNull();

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/config/telegram/deactivate')
            ->assertSuccessful();

        $first->refresh();

        expect((bool) $first->active)->toBeFalse()
            ->and($first->priority)->toBeNull();
    });

    it('allows one direct telegram transport', function () {
        ConfigTelegram::create([
            'name' => 'failover-test-direct',
            'type' => 'direct',
            'host' => null,
            'port' => null,
            'username' => '',
            'password' => '',
            'active' => false,
            'priority' => null,
        ]);

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/config/telegram', [
                'name' => 'failover-test-direct-again',
                'type' => 'direct',
            ])
            ->assertUnprocessable();
    });
});
