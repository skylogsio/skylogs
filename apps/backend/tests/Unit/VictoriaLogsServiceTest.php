<?php

use App\Models\DataSource\DataSource;
use App\Models\VictoriaLogsCheck;
use App\Services\VictoriaLogsService;
use Illuminate\Support\Facades\Http;
use Tests\Support\Factories\AlertRuleFactory;

function makeVictoriaLogsCheckForTest(array $checkAttributes = []): VictoriaLogsCheck
{
    $dataSource = DataSource::withoutEvents(function () {
        $model = new DataSource;
        $model->setAttribute('url', 'https://victoria.example.com');

        return $model;
    });

    $alertRule = AlertRuleFactory::unsaved([
        'dataSourceId' => 'ds-1',
    ]);
    $alertRule->setRelation('dataSource', $dataSource);

    $check = VictoriaLogsCheck::withoutEvents(function () use ($checkAttributes) {
        $model = new VictoriaLogsCheck;
        $model->setAttribute('queryString', 'status:failed');
        $model->setAttribute('minutes', 5);

        foreach ($checkAttributes as $key => $value) {
            $model->setAttribute($key, $value);
        }

        return $model;
    });
    $check->setRelation('alertRule', $alertRule);

    return $check;
}

describe('VictoriaLogsService::countDocuments', function () {
    it('returns the count from the victoria logs query endpoint', function () {
        Http::fake([
            'victoria.example.com/*' => Http::response(['total' => 12], 200),
        ]);

        expect(VictoriaLogsService::countDocuments(makeVictoriaLogsCheckForTest()))->toBe(12);
    });

    it('returns null when the victoria logs request fails', function () {
        Http::fake([
            'victoria.example.com/*' => Http::failedConnection(),
        ]);

        expect(VictoriaLogsService::countDocuments(makeVictoriaLogsCheckForTest()))->toBeNull();
    });

    it('returns null when victoria logs responds with an error status', function () {
        Http::fake([
            'victoria.example.com/*' => Http::response('service unavailable', 503),
        ]);

        expect(VictoriaLogsService::countDocuments(makeVictoriaLogsCheckForTest()))->toBeNull();
    });

    it('returns null when victoria logs responds without a total field', function () {
        Http::fake([
            'victoria.example.com/*' => Http::response([], 200),
        ]);

        expect(VictoriaLogsService::countDocuments(makeVictoriaLogsCheckForTest()))->toBeNull();
    });
});
