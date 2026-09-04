<?php

use App\Models\DataSource\DataSource;
use App\Models\ElasticCheck;
use App\Services\ElasticService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\Factories\AlertRuleFactory;

function makeElasticCheckForTest(array $checkAttributes = []): ElasticCheck
{
    $dataSource = DataSource::withoutEvents(function () {
        $model = new DataSource;
        $model->setAttribute('url', 'https://elastic.example.com');
        $model->setAttribute('username', 'elastic-user');
        $model->setAttribute('password', 'elastic-pass');

        return $model;
    });

    $alertRule = AlertRuleFactory::unsaved([
        'dataSourceId' => 'ds-1',
    ]);
    $alertRule->setRelation('dataSource', $dataSource);

    $check = ElasticCheck::withoutEvents(function () use ($checkAttributes) {
        $model = new ElasticCheck;
        $model->setAttribute('dataviewTitle', 'tr-cdn*');
        $model->setAttribute('queryString', 'domain:test.io status:>=500');
        $model->setAttribute('minutes', 5);
        $model->setAttribute('countDocument', 4);

        foreach ($checkAttributes as $key => $value) {
            $model->setAttribute($key, $value);
        }

        return $model;
    });
    $check->setRelation('alertRule', $alertRule);

    return $check;
}

describe('ElasticService::countDocuments', function () {
    afterEach(function () {
        Carbon::setTestNow();
    });

    it('returns the count from the elasticsearch _count endpoint', function () {
        Carbon::setTestNow(Carbon::parse('2026-06-14 12:05:00', 'UTC'));

        Http::fake([
            'elastic.example.com/*' => Http::response(['count' => 47], 200),
        ]);

        $count = ElasticService::countDocuments(makeElasticCheckForTest());

        expect($count)->toBe(47);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://elastic.example.com/tr-cdn*/_count'
                && $request['query']['query_string']['query'] === 'timestamp:[2026-06-14T12:00:00 TO 2026-06-14T12:05:00] domain:test.io status:>=500'
                && $request['query']['query_string']['default_operator'] === 'AND';
        });
    });

    it('returns zero when elasticsearch responds without a count', function () {
        Http::fake([
            'elastic.example.com/*' => Http::response([], 200),
        ]);

        expect(ElasticService::countDocuments(makeElasticCheckForTest()))->toBe(0);
    });

    it('returns null when the elasticsearch request fails', function () {
        Http::fake([
            'elastic.example.com/*' => Http::failedConnection(),
        ]);

        expect(ElasticService::countDocuments(makeElasticCheckForTest()))->toBeNull();
    });

    it('returns null when elasticsearch responds with an error status', function () {
        Http::fake([
            'elastic.example.com/*' => Http::response('service unavailable', 503),
        ]);

        expect(ElasticService::countDocuments(makeElasticCheckForTest()))->toBeNull();
    });
});
