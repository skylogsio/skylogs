<?php

use App\Enums\AlertRuleType;
use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Models\ElasticCheck;
use App\Models\GrafanaCheck;
use App\Models\HealthCheck;
use App\Models\PrometheusCheck;
use App\Models\VictoriaLogsCheck;
use App\Models\ZabbixCheck;
use App\Observers\Ha\HaAlertRuleObserver;
use App\Observers\Ha\HaCheckObserver;
use App\Services\Ha\AlertStateReplicator;
use Illuminate\Support\Facades\Event;
use Tests\Support\Factories\AlertRuleFactory;

function haObservedRule(): AlertRule
{
    return AlertRuleFactory::unsaved([
        '_id' => '6512ab000000000000000001',
        'name' => 'node down',
        'type' => AlertRuleType::PROMETHEUS,
        'state' => AlertRule::CRITICAL,
    ]);
}

function haObservedCheck(AlertRule $alertRule): PrometheusCheck
{
    $check = new PrometheusCheck;
    $check->alertRuleId = $alertRule->getKey();
    $check->setRelation('alertRule', $alertRule);

    return $check;
}

describe('HA observers', function () {
    it('watches every model that carries replicated state', function (string $model) {
        expect(Event::hasListeners('eloquent.saved: '.$model))->toBeTrue()
            ->and(Event::hasListeners('eloquent.deleted: '.$model))->toBeTrue();
    })->with([
        AlertRule::class,
        PrometheusCheck::class,
        GrafanaCheck::class,
        ZabbixCheck::class,
        AlertInstance::class,
        ElasticCheck::class,
        VictoriaLogsCheck::class,
        HealthCheck::class,
    ]);

    it('replicates a saved check document', function () {
        $alertRule = haObservedRule();
        $check = haObservedCheck($alertRule);

        $replicator = Mockery::mock(AlertStateReplicator::class);
        $replicator->shouldReceive('shouldReplicate')->andReturnTrue();
        $replicator->shouldReceive('replicateCheck')->once()->with($check, $alertRule);

        (new HaCheckObserver($replicator))->saved($check);
    });

    it('replicates a deleted check document', function () {
        $alertRule = haObservedRule();
        $check = haObservedCheck($alertRule);

        $replicator = Mockery::mock(AlertStateReplicator::class);
        $replicator->shouldReceive('shouldReplicate')->andReturnTrue();
        $replicator->shouldReceive('replicateCheckDeletion')->once()->with($check, $alertRule);

        (new HaCheckObserver($replicator))->deleted($check);
    });

    it('never looks the alert rule up when replication is off', function () {
        $check = haObservedCheck(haObservedRule());

        $replicator = Mockery::mock(AlertStateReplicator::class);
        $replicator->shouldReceive('shouldReplicate')->andReturnFalse();
        $replicator->shouldNotReceive('replicateCheck');

        (new HaCheckObserver($replicator))->saved($check);
    });

    it('replicates a saved alert rule', function () {
        $alertRule = haObservedRule();

        $replicator = Mockery::mock(AlertStateReplicator::class);
        $replicator->shouldReceive('replicateRule')->once()->with($alertRule);

        (new HaAlertRuleObserver($replicator))->saved($alertRule);
    });

    it('sweeps the slots of a deleted alert rule', function () {
        $alertRule = haObservedRule();

        $replicator = Mockery::mock(AlertStateReplicator::class);
        $replicator->shouldReceive('replicateRuleDeletion')->once()->with($alertRule);

        (new HaAlertRuleObserver($replicator))->deleted($alertRule);
    });
});
