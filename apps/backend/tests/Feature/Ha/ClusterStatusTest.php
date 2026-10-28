<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use MongoDB\BSON\ObjectId;

/**
 * @return array<string, mixed>
 */
function haClusterStatusPayload(): array
{
    return [
        'reporter_id' => 'node2',
        'timestamp' => '2026-10-28T13:13:07.41636941Z',
        'local_state' => 'Leader',
        'known_leader_id' => 'node2',
        'known_leader_address' => '172.28.7.12:7000',
        'peers' => [
            [
                'id' => 'node1',
                'address' => '172.28.7.11:7000',
                'suffrage' => 'Voter',
                'tcp_reachable' => true,
            ],
            [
                'id' => 'node2',
                'address' => '172.28.7.12:7000',
                'suffrage' => 'Voter',
                'tcp_reachable' => true,
            ],
            [
                'id' => 'node3',
                'address' => '172.28.7.13:7000',
                'suffrage' => 'Voter',
                'tcp_reachable' => true,
            ],
        ],
    ];
}

function haClusterStatusUser(): User
{
    $user = new User([
        'name' => 'Cluster Status Tester',
        'username' => 'cluster-status-'.uniqid(),
    ]);
    $user->_id = new ObjectId;

    return $user;
}

function haClusterStatusRequest(?User $user = null): TestResponse
{
    $request = test();

    if ($user !== null) {
        $request = $request->actingAs($user, 'api');
    }

    return $request->getJson(route('ha.clusterStatus'));
}

beforeEach(function () {
    config([
        'ha.raft.url' => 'http://raft.test:8000',
        'ha.raft.connect_timeout' => 0.5,
        'ha.raft.timeout' => [
            'status' => 1,
            'cluster_status' => 1,
            'leader' => 1,
            'set' => 3,
            'get' => 3,
        ],
        'ha.raft.retry_attempts' => 2,
        'ha.raft.retry_sleep_milliseconds' => 0,
    ]);
});

describe('GET /api/v1/ha/cluster/status authentication', function () {
    it('rejects unauthenticated requests', function () {
        haClusterStatusRequest()->assertUnauthorized();
    });
});

describe('GET /api/v1/ha/cluster/status', function () {
    it('returns the raft cluster status payload as json', function () {
        $payload = haClusterStatusPayload();

        Http::fake([
            'raft.test:8000/cluster/status' => Http::response($payload),
        ]);

        haClusterStatusRequest(haClusterStatusUser())
            ->assertOk()
            ->assertExactJson($payload);

        Http::assertSent(fn ($request) => $request->url() === 'http://raft.test:8000/cluster/status');
    });

    it('returns 503 when the raft sidecar is unreachable', function () {
        Http::fake([
            'raft.test:8000/cluster/status' => Http::failedConnection(),
        ]);

        haClusterStatusRequest(haClusterStatusUser())
            ->assertStatus(503)
            ->assertJsonStructure(['message']);
    });
});
