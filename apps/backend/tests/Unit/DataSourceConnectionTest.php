<?php

use App\Enums\DataSourceType;
use App\Models\DataSource\DataSource;
use App\Services\DataSourceService;
use App\Services\Health\DataSourceProbes\DataSourceProbeFactory;
use App\Services\Health\HealthProbe;
use App\Services\Health\HealthProbeSender;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

describe('data source connection checks', function () {
    afterEach(function () {
        if (isset($this->dataSource)) {
            DataSource::query()->where('_id', $this->dataSource->_id)->delete();
        }
    });

    it('probes each data source type with its own endpoint and credentials', function (
        DataSourceType $type,
        array $credentials,
        string $url,
        string $method,
        ?string $authorization,
        int $status,
        string $body,
        bool $connected,
    ) {
        Http::preventStrayRequests();
        Http::fake([
            'source.example.com/*' => Http::response($body, $status),
        ]);

        $this->dataSource = DataSource::withoutEvents(fn () => DataSource::create([
            'name' => 'Connection '.$type->value,
            'type' => $type,
            'url' => 'https://source.example.com',
            ...$credentials,
        ]));

        $isConnected = app(DataSourceService::class)->isConnected((string) $this->dataSource->_id);

        expect($isConnected)->toBe($connected);

        Http::assertSent(function (Request $request) use ($url, $method, $authorization) {
            $header = $request->header('Authorization')[0] ?? null;

            return $request->url() === $url
                && $request->method() === $method
                && $header === $authorization;
        });
    })->with([
        'prometheus' => [
            DataSourceType::PROMETHEUS,
            ['username' => 'prom-user', 'password' => 'prom-pass'],
            'https://source.example.com/api/v1/alerts',
            'GET',
            'Basic '.base64_encode('prom-user:prom-pass'),
            200,
            '{"status":"success","data":{"alerts":[]}}',
            true,
        ],
        'prometheus unauthorized' => [
            DataSourceType::PROMETHEUS,
            ['username' => 'prom-user', 'password' => 'prom-pass'],
            'https://source.example.com/api/v1/alerts',
            'GET',
            'Basic '.base64_encode('prom-user:prom-pass'),
            401,
            '',
            false,
        ],
        'grafana' => [
            DataSourceType::GRAFANA,
            ['username' => 'graf-user', 'password' => 'graf-pass'],
            'https://source.example.com/api/health',
            'GET',
            'Basic '.base64_encode('graf-user:graf-pass'),
            200,
            '{"database":"ok"}',
            true,
        ],
        'pmm' => [
            DataSourceType::PMM,
            ['username' => 'pmm-user', 'password' => 'pmm-pass'],
            'https://source.example.com/api/health',
            'GET',
            'Basic '.base64_encode('pmm-user:pmm-pass'),
            200,
            'ok',
            true,
        ],
        'zabbix' => [
            DataSourceType::ZABBIX,
            [],
            'https://source.example.com/api_jsonrpc.php',
            'POST',
            null,
            200,
            '{"jsonrpc":"2.0","result":"6.0.0","id":1}',
            true,
        ],
        'zabbix api error' => [
            DataSourceType::ZABBIX,
            [],
            'https://source.example.com/api_jsonrpc.php',
            'POST',
            null,
            200,
            '{"error":{"code":-32600}}',
            false,
        ],
        'elastic' => [
            DataSourceType::ELASTIC,
            ['username' => 'elastic-user', 'password' => 'elastic-pass'],
            'https://source.example.com/_cluster/health',
            'GET',
            'Basic '.base64_encode('elastic-user:elastic-pass'),
            200,
            '{"status":"green"}',
            true,
        ],
        'elastic yellow' => [
            DataSourceType::ELASTIC,
            ['username' => 'elastic-user', 'password' => 'elastic-pass'],
            'https://source.example.com/_cluster/health',
            'GET',
            'Basic '.base64_encode('elastic-user:elastic-pass'),
            200,
            '{"status":"yellow"}',
            true,
        ],
        'elastic red' => [
            DataSourceType::ELASTIC,
            ['username' => 'elastic-user', 'password' => 'elastic-pass'],
            'https://source.example.com/_cluster/health',
            'GET',
            'Basic '.base64_encode('elastic-user:elastic-pass'),
            200,
            '{"status":"red"}',
            false,
        ],
        'sentry' => [
            DataSourceType::SENTRY,
            ['apiToken' => 'sentry-token'],
            'https://source.example.com/api/0/',
            'GET',
            'Bearer sentry-token',
            200,
            '{}',
            true,
        ],
        'victoria logs' => [
            DataSourceType::VICTORIA_LOGS,
            [],
            'https://source.example.com/health',
            'GET',
            null,
            200,
            'OK',
            true,
        ],
        'splunk token' => [
            DataSourceType::SPLUNK,
            ['apiToken' => 'splunk-token', 'username' => 'splunk-user', 'password' => 'splunk-pass'],
            'https://source.example.com/services/server/info?output_mode=json',
            'GET',
            'Bearer splunk-token',
            200,
            '{}',
            true,
        ],
        'splunk basic' => [
            DataSourceType::SPLUNK,
            ['username' => 'splunk-user', 'password' => 'splunk-pass'],
            'https://source.example.com/services/server/info?output_mode=json',
            'GET',
            'Basic '.base64_encode('splunk-user:splunk-pass'),
            200,
            '{}',
            true,
        ],
        'splunk down' => [
            DataSourceType::SPLUNK,
            ['username' => 'splunk-user', 'password' => 'splunk-pass'],
            'https://source.example.com/services/server/info?output_mode=json',
            'GET',
            'Basic '.base64_encode('splunk-user:splunk-pass'),
            503,
            '',
            false,
        ],
    ]);

    it('returns false when the data source cannot be reached', function () {
        Http::fake([
            'source.example.com/*' => Http::failedConnection(),
        ]);

        $this->dataSource = DataSource::withoutEvents(fn () => DataSource::create([
            'name' => 'Unreachable prometheus',
            'type' => DataSourceType::PROMETHEUS,
            'url' => 'https://source.example.com',
        ]));

        expect(app(DataSourceService::class)->isConnected((string) $this->dataSource->_id))->toBeFalse();
    });

    it('returns false without calling out when the url is empty', function () {
        Http::preventStrayRequests();

        $this->dataSource = DataSource::withoutEvents(fn () => DataSource::create([
            'name' => 'Blank url',
            'type' => DataSourceType::GRAFANA,
            'url' => '',
        ]));

        expect(app(DataSourceService::class)->isConnected((string) $this->dataSource->_id))->toBeFalse();

        Http::assertNothingSent();
    });

    it('fails when the data source does not exist', function () {
        expect(fn () => app(DataSourceService::class)->isConnected('000000000000000000000000'))
            ->toThrow(ModelNotFoundException::class);
    });

    it('sends zabbix probes without tls verification and with the version payload', function () {
        Http::preventStrayRequests();
        Http::fake([
            'source.example.com/*' => Http::response('{"jsonrpc":"2.0","result":"6.0.0","id":1}', 200),
        ]);

        $this->dataSource = DataSource::withoutEvents(fn () => DataSource::create([
            'name' => 'Zabbix tls',
            'type' => DataSourceType::ZABBIX,
            'url' => 'https://source.example.com',
        ]));

        $probe = app(DataSourceProbeFactory::class)->for($this->dataSource);
        $options = app(HealthProbeSender::class)
            ->pending(Http::createPendingRequest(), $probe)
            ->getOptions();

        expect(app(DataSourceService::class)->isConnected((string) $this->dataSource->_id))->toBeTrue()
            ->and($options['verify'])->toBeFalse();

        Http::assertSent(function (Request $request) {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://source.example.com/api_jsonrpc.php'
                && ($payload['jsonrpc'] ?? null) === '2.0'
                && ($payload['method'] ?? null) === 'apiinfo.version'
                && ($payload['id'] ?? null) === 1
                && (array) ($payload['params'] ?? null) === [];
        });
    });
});

describe('health probe sender', function () {
    it('applies the probe timeout and tls settings', function () {
        $probe = new HealthProbe(
            method: 'GET',
            url: 'https://source.example.com/health',
            verifyTls: false,
            timeoutSeconds: 4,
        );

        $options = app(HealthProbeSender::class)
            ->pending(Http::createPendingRequest(), $probe)
            ->getOptions();

        expect($options['connect_timeout'])->toBe(HealthProbe::CONNECT_TIMEOUT_SECONDS)
            ->and($options['timeout'])->toBe(4)
            ->and($options['verify'])->toBeFalse();
    });
});
