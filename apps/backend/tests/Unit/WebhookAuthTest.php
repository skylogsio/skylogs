<?php

use App\Enums\AlertRuleType;
use App\Enums\DataSourceType;
use App\Http\Middleware\WebhookAuth;
use App\Services\ApiService;
use App\Services\DataSourceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

describe('WebhookAuth', function () {
    it('looks up the webhook token on the service instead of the cached datasource list', function () {
        $dataSourceService = Mockery::mock(DataSourceService::class);
        $dataSourceService->shouldReceive('byWebhookToken')
            ->once()
            ->with('grafana-token', DataSourceType::GRAFANA)
            ->andReturn(null);
        $dataSourceService->shouldNotReceive('get');

        $request = Request::create('/grafana-alert/grafana-token', 'POST');
        $route = (new Route(['POST'], 'grafana-alert/{token}', []))->name('webhook.grafana');
        $route->bind($request);
        $route->setParameter('token', 'grafana-token');
        $request->setRouteResolver(fn () => $route);

        $middleware = new WebhookAuth($dataSourceService);

        try {
            $middleware->handle($request, fn () => response('ok'));
            expect(false)->toBeTrue();
        } catch (HttpException $exception) {
            expect($exception->getStatusCode())->toBe(403);
        }
    });
});

describe('token lookups skip cache', function () {
    it('returns null for an empty webhook token', function () {
        expect(app(DataSourceService::class)->byWebhookToken('', DataSourceType::GRAFANA))->toBeNull();
    });

    it('returns null for an empty api alert token', function () {
        expect(app(ApiService::class)->alertRuleByToken('', AlertRuleType::API))->toBeNull();
    });
});
