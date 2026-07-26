<?php

use App\Jobs\Ha\PublishAlertStateJob;
use App\Models\AlertRule;
use App\Services\Ha\AlertStateReplicator;
use App\Services\Ha\HaLeaderService;
use App\Services\Ha\HaReconciler;
use App\Services\Ha\HaStateApplier;
use App\Services\Ha\RaftClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Ha\InMemoryHaStateVersionStore;

const RECONCILE_RULE_ID = '6512ab000000000000000001';

function haRemoteSlot(string $instanceId, int $version, string $state = AlertRule::CRITICAL, string $nodeId = 'node-1'): array
{
    return [
        'key' => 'alert:'.RECONCILE_RULE_ID.':prometheus:'.$instanceId,
        'version' => $version,
        'nodeId' => $nodeId,
        'state' => $state,
        'alertRuleId' => RECONCILE_RULE_ID,
        'type' => 'prometheus',
    ];
}

function haReconciler(bool $isLeader, array $remoteData): HaReconciler
{
    $raft = Mockery::mock(RaftClient::class);
    $raft->shouldReceive('getAll')->andReturn($remoteData);

    $leader = Mockery::mock(HaLeaderService::class);
    $leader->shouldReceive('isLeader')->andReturn($isLeader);

    return new HaReconciler(
        $raft,
        $leader,
        test()->applier,
        test()->versions,
        test()->replicator,
    );
}

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'ha.enabled' => true,
        'ha.node_id' => 'node-2',
        'ha.state_retention_days' => 7,
    ]);

    CarbonImmutable::setTestNow('2026-07-26 00:00:00');

    $this->versions = new InMemoryHaStateVersionStore;
    $this->applier = Mockery::mock(HaStateApplier::class);
    $this->replicator = Mockery::mock(AlertStateReplicator::class);

    Queue::fake();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

describe('HaReconciler on a follower', function () {
    it('applies every key the log holds through the same version gate', function () {
        $data = [
            'alert:'.RECONCILE_RULE_ID.':prometheus:aaa' => haRemoteSlot('aaa', 4),
            'alert:'.RECONCILE_RULE_ID.':prometheus:bbb' => haRemoteSlot('bbb', 7),
        ];

        $this->applier->shouldReceive('apply')
            ->with('alert:'.RECONCILE_RULE_ID.':prometheus:aaa', $data['alert:'.RECONCILE_RULE_ID.':prometheus:aaa'])
            ->once()
            ->andReturn(['applied' => true]);

        $this->applier->shouldReceive('apply')
            ->with('alert:'.RECONCILE_RULE_ID.':prometheus:bbb', $data['alert:'.RECONCILE_RULE_ID.':prometheus:bbb'])
            ->once()
            ->andReturn(['applied' => false, 'reason' => HaStateApplier::REASON_STALE]);

        expect(haReconciler(isLeader: false, remoteData: $data)->reconcile())
            ->toBe(['role' => 'follower', 'applied' => 1, 'removed' => 0]);
    });

    it('drops local slots the log no longer carries', function () {
        $this->versions->seed('alert:'.RECONCILE_RULE_ID.':prometheus:gone', 3, 'node-1');

        $this->applier->shouldReceive('apply')
            ->with('alert:'.RECONCILE_RULE_ID.':prometheus:gone', null)
            ->once()
            ->andReturn(['applied' => true]);

        expect(haReconciler(isLeader: false, remoteData: [])->reconcile())
            ->toBe(['role' => 'follower', 'applied' => 0, 'removed' => 1]);
    });

    it('keeps a local slot the log still carries', function () {
        $key = 'alert:'.RECONCILE_RULE_ID.':prometheus:aaa';
        $this->versions->seed($key, 3, 'node-1');

        $this->applier->shouldReceive('apply')->with($key, Mockery::type('array'))->once()->andReturn(['applied' => true]);
        $this->applier->shouldNotReceive('apply')->with($key, null);

        expect(haReconciler(isLeader: false, remoteData: [$key => haRemoteSlot('aaa', 4)])->reconcile()['removed'])
            ->toBe(0);
    });

    it('never publishes anything back into the log', function () {
        $this->applier->shouldReceive('apply')->andReturn(['applied' => true]);

        haReconciler(isLeader: false, remoteData: [
            'alert:'.RECONCILE_RULE_ID.':prometheus:aaa' => haRemoteSlot('aaa', 4),
        ])->reconcile();

        Queue::assertNothingPushed();
    });
});

describe('HaReconciler on a leader', function () {
    it('inherits the log counters so a promoted node does not restart its versions', function () {
        $key = 'alert:'.RECONCILE_RULE_ID.':prometheus:aaa';
        $this->versions->seed($key, 2, 'node-2', AlertRule::CRITICAL);

        $summary = haReconciler(isLeader: true, remoteData: [$key => haRemoteSlot('aaa', 11)])->reconcile();

        expect($summary['inherited'])->toBe(1)
            ->and($this->versions->current($key))->toBe(11)
            ->and($summary['republished'])->toBe(0);
    });

    it('leaves its own counter alone when the log is behind', function () {
        $key = 'alert:'.RECONCILE_RULE_ID.':prometheus:aaa';
        $this->versions->seed($key, 11, 'node-2', AlertRule::CRITICAL);

        $this->replicator->shouldReceive('republishRule')->never();

        $reconciler = haReconciler(isLeader: true, remoteData: [$key => haRemoteSlot('aaa', 11)]);

        expect($reconciler->reconcile()['inherited'])->toBe(0)
            ->and($this->versions->current($key))->toBe(11);
    });

    it('tombstones resolved slots that have outlived the retention window', function () {
        $expired = 'alert:'.RECONCILE_RULE_ID.':prometheus:old';
        $recent = 'alert:'.RECONCILE_RULE_ID.':prometheus:new';

        $this->versions->seed($expired, 4, 'node-2', AlertRule::RESOlVED, CarbonImmutable::now()->subDays(30));
        $this->versions->seed($recent, 4, 'node-2', AlertRule::RESOlVED, CarbonImmutable::now()->subHour());

        $summary = haReconciler(isLeader: true, remoteData: [
            $expired => haRemoteSlot('old', 4, AlertRule::RESOlVED),
            $recent => haRemoteSlot('new', 4, AlertRule::RESOlVED),
        ])->reconcile();

        expect($summary['swept'])->toBe(1)
            ->and($this->versions->allKeys())->toBe([$recent]);

        Queue::assertPushed(PublishAlertStateJob::class, 1);
        Queue::assertPushed(
            PublishAlertStateJob::class,
            fn (PublishAlertStateJob $job): bool => $job->key === $expired && $job->value === null,
        );
    });

    it('keeps resolved slots when retention is switched off', function () {
        config(['ha.state_retention_days' => 0]);

        $key = 'alert:'.RECONCILE_RULE_ID.':prometheus:old';
        $this->versions->seed($key, 4, 'node-2', AlertRule::RESOlVED, CarbonImmutable::now()->subDays(365));

        $summary = haReconciler(isLeader: true, remoteData: [$key => haRemoteSlot('old', 4, AlertRule::RESOlVED)])->reconcile();

        expect($summary['swept'])->toBe(0)
            ->and($this->versions->allKeys())->toBe([$key]);
    });

    it('forgets a local counter whose key can no longer be parsed', function () {
        $this->versions->seed('rubbish', 4, 'node-2');

        haReconciler(isLeader: true, remoteData: [])->reconcile();

        expect($this->versions->allKeys())->toBe([]);
    });
});

describe('HaReconciler with ha switched off', function () {
    it('does nothing at all, so a single node install never talks to a sidecar', function () {
        config(['ha.enabled' => false]);

        $raft = Mockery::mock(RaftClient::class);
        $raft->shouldNotReceive('getAll');

        $leader = Mockery::mock(HaLeaderService::class);

        $reconciler = new HaReconciler($raft, $leader, $this->applier, $this->versions, $this->replicator);

        expect($reconciler->reconcile())->toBe(['role' => 'standalone']);
    });
});
