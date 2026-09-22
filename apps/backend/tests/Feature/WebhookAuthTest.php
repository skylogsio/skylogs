<?php

use App\Enums\Constants;
use App\Enums\DataSourceType;
use App\Models\DataSource\DataSource;
use App\Services\DataSourceService;
use Illuminate\Support\Facades\Cache;
use Tests\Support\TeamTestData;

describe('WebhookAuth', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->webhookToken = 'grafana-hook-'.uniqid();
        $this->dataSource = DataSource::create([
            'name' => 'Grafana Auth '.uniqid(),
            'type' => DataSourceType::GRAFANA,
            'url' => 'https://grafana.example.com',
            'userId' => $this->owner->id,
            'webhookToken' => $this->webhookToken,
        ]);
    });

    afterEach(function () {

        if (isset($this->dataSource)) {
            DataSource::query()->where('_id', $this->dataSource->_id)->delete();
        }

        if (isset($this->owner)) {
            TeamTestData::deleteUser($this->owner);
        }

        Cache::flush();
    });

    it('authenticates a grafana webhook from the database when the datasource cache is empty', function () {
        Cache::tags(['dataSource', DataSourceType::GRAFANA->value])->forever('dataSource:grafana', collect());

        $this->postJson('/api/v1/grafana-alert/'.$this->webhookToken, [
            'alerts' => [],
        ])->assertUnprocessable()
            ->assertSee('Alert rule not found');
    });

    it('rejects an unknown webhook token', function () {
        $this->postJson('/api/v1/grafana-alert/wrong-token', [
            'alerts' => [],
        ])->assertForbidden();
    });

    it('looks up a webhook token without reading the cached datasource list', function () {
        Cache::tags(['dataSource', DataSourceType::GRAFANA->value])->forever('dataSource:grafana', collect());

        $dataSource = app(DataSourceService::class)->byWebhookToken($this->webhookToken, DataSourceType::GRAFANA);

        expect($dataSource)->not->toBeNull()
            ->and((string) $dataSource->id)->toBe((string) $this->dataSource->id);
    });
});
