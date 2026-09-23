<?php

use App\Enums\DataSourceType;
use App\Enums\HealthAlertType;
use App\Models\AlertRule;
use App\Models\DataSource\DataSource;
use App\Services\Health\HealthProbe;
use App\Services\Health\Targets\DataSourceTarget;
use App\Services\Health\Targets\HttpTarget;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;

function healthHttpResponse(int $status, string $body = ''): Response
{
    return new Response(new PsrResponse($status, [], $body));
}

describe('health target probes', function () {
    afterEach(function () {
        if (isset($this->dataSource)) {
            DataSource::query()->where('_id', $this->dataSource->_id)->delete();
        }
    });

    it('builds a datasource probe', function (
        DataSourceType $type,
        array $credentials,
        string $url,
        string $method,
        ?string $username,
        ?string $token,
        bool $verifyTls,
        int $status,
        string $body,
        ?string $error,
    ) {
        $this->dataSource = DataSource::create([
            'name' => 'Probe '.$type->value,
            'type' => $type,
            'url' => 'https://source.example.com',
            ...$credentials,
        ]);

        $rule = new AlertRule([
            'checkType' => HealthAlertType::DATASOURCE,
            'target' => ['dataSourceId' => (string) $this->dataSource->_id],
        ]);

        $probe = app(DataSourceTarget::class)->probe($rule);

        expect($probe->url)->toBe($url)
            ->and($probe->method)->toBe($method)
            ->and($probe->username)->toBe($username)
            ->and($probe->bearerToken)->toBe($token)
            ->and($probe->verifyTls)->toBe($verifyTls)
            ->and($probe->errorFor(healthHttpResponse($status, $body)))->toBe($error);
    })->with([
        'prometheus' => [
            DataSourceType::PROMETHEUS,
            ['username' => 'prom-user', 'password' => 'prom-pass'],
            'https://source.example.com/api/v1/alerts',
            'GET',
            'prom-user',
            null,
            true,
            200,
            '[]',
            null,
        ],
        'grafana' => [
            DataSourceType::GRAFANA,
            ['username' => 'graf-user', 'password' => 'graf-pass'],
            'https://source.example.com/api/health',
            'GET',
            'graf-user',
            null,
            true,
            200,
            '{"database":"ok"}',
            null,
        ],
        'pmm' => [
            DataSourceType::PMM,
            ['username' => 'pmm-user', 'password' => 'pmm-pass'],
            'https://source.example.com/api/health',
            'GET',
            'pmm-user',
            null,
            true,
            200,
            'ok',
            null,
        ],
        'zabbix' => [
            DataSourceType::ZABBIX,
            [],
            'https://source.example.com/api_jsonrpc.php',
            'POST',
            null,
            null,
            false,
            200,
            '{"jsonrpc":"2.0","result":"6.0.0","id":1}',
            null,
        ],
        'zabbix error' => [
            DataSourceType::ZABBIX,
            [],
            'https://source.example.com/api_jsonrpc.php',
            'POST',
            null,
            null,
            false,
            200,
            '{"error":{"code":-32600}}',
            'Zabbix API error',
        ],
        'elastic' => [
            DataSourceType::ELASTIC,
            ['username' => 'elastic-user', 'password' => 'elastic-pass'],
            'https://source.example.com/_cluster/health',
            'GET',
            'elastic-user',
            null,
            true,
            200,
            '{"status":"green"}',
            null,
        ],
        'elastic red' => [
            DataSourceType::ELASTIC,
            ['username' => 'elastic-user', 'password' => 'elastic-pass'],
            'https://source.example.com/_cluster/health',
            'GET',
            'elastic-user',
            null,
            true,
            200,
            '{"status":"red"}',
            'cluster status red',
        ],
        'sentry' => [
            DataSourceType::SENTRY,
            ['apiToken' => 'sentry-token'],
            'https://source.example.com/api/0/',
            'GET',
            null,
            'sentry-token',
            true,
            200,
            '{}',
            null,
        ],
        'victoria logs' => [
            DataSourceType::VICTORIA_LOGS,
            [],
            'https://source.example.com/health',
            'GET',
            null,
            null,
            true,
            200,
            'OK',
            null,
        ],
        'splunk token' => [
            DataSourceType::SPLUNK,
            ['apiToken' => 'splunk-token', 'username' => 'splunk-user', 'password' => 'splunk-pass'],
            'https://source.example.com/services/server/info?output_mode=json',
            'GET',
            null,
            'splunk-token',
            true,
            200,
            '{}',
            null,
        ],
        'splunk basic' => [
            DataSourceType::SPLUNK,
            ['username' => 'splunk-user', 'password' => 'splunk-pass'],
            'https://source.example.com/services/server/info?output_mode=json',
            'GET',
            'splunk-user',
            null,
            true,
            503,
            '',
            'HTTP 503',
        ],
    ]);

    it('honours a generic http target', function () {
        $rule = new AlertRule([
            'checkType' => HealthAlertType::HTTP,
            'target' => [
                'url' => 'https://example.com/status',
                'method' => 'POST',
                'headers' => [
                    ['key' => 'X-Check', 'value' => '1'],
                ],
                'body' => '{"ping":true}',
                'expectedStatuses' => [204],
                'bodyContains' => 'pong',
                'timeoutSeconds' => 12,
                'verifyTls' => false,
            ],
        ]);

        $probe = app(HttpTarget::class)->probe($rule);

        expect($probe->method)->toBe('POST')
            ->and($probe->url)->toBe('https://example.com/status')
            ->and($probe->headers)->toBe(['X-Check' => '1'])
            ->and($probe->body)->toBe('{"ping":true}')
            ->and($probe->timeoutSeconds)->toBe(12)
            ->and($probe->effectiveTimeout())->toBe(HealthProbe::MAX_TIMEOUT_SECONDS)
            ->and($probe->verifyTls)->toBeFalse()
            ->and($probe->errorFor(healthHttpResponse(204, 'pong')))->toBeNull()
            ->and($probe->errorFor(healthHttpResponse(200, 'pong')))->toBe('HTTP 200')
            ->and($probe->errorFor(healthHttpResponse(204, 'no')))->toBe('response body mismatch');
    });
});
