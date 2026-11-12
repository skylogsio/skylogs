<?php

use App\Models\SkylogsInstance;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use MongoDB\BSON\ObjectId;

function instanceStatusUser(): User
{
    $user = new User([
        'name' => 'Instance Status Tester',
        'username' => 'instance-status-'.uniqid(),
    ]);
    $user->_id = new ObjectId;

    return $user;
}

/**
 * @return array<string, mixed>
 */
function instanceRaftClusterPayload(): array
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
        ],
    ];
}

function createInstanceStatusTarget(): SkylogsInstance
{
    return SkylogsInstance::create([
        'name' => 'Agent '.uniqid(),
        'type' => 'agent',
        'url' => 'http://instance-status.test',
        'token' => 'instance-token-'.uniqid(),
    ]);
}

describe('GET /api/v1/skylogs-instance/status/{id}', function () {
    beforeEach(function () {
        $this->instance = createInstanceStatusTarget();
    });

    afterEach(function () {
        SkylogsInstance::query()->where('_id', $this->instance->_id)->delete();
    });

    it('returns the remote raft cluster status when the instance is connected', function () {
        $cluster = instanceRaftClusterPayload();
        $token = 'instance-status-token';

        Http::fake([
            'http://instance-status.test/api/v1/ha/cluster/status' => Http::response([
                'enabled' => true,
                'cluster' => $cluster,
            ]),
            'http://instance-status.test' => Http::response(['message' => 'Welcome to Skylogs!']),
        ]);

        $this->actingAs(instanceStatusUser(), 'api')
            ->withToken($token)
            ->getJson('/api/v1/skylogs-instance/status/'.$this->instance->id)
            ->assertSuccessful()
            ->assertExactJson([
                'isConnected' => true,
                'enabled' => true,
                'cluster' => $cluster,
            ]);

        Http::assertSent(function ($request) use ($token) {
            return $request->url() === 'http://instance-status.test/api/v1/ha/cluster/status'
                && $request->hasHeader('Authorization', 'Bearer '.$token);
        });
    });

    it('returns a disabled cluster when the remote instance has high availability turned off', function () {
        Http::fake([
            'http://instance-status.test/api/v1/ha/cluster/status' => Http::response([
                'enabled' => false,
                'cluster' => null,
            ]),
            'http://instance-status.test' => Http::response(['message' => 'Welcome to Skylogs!']),
        ]);

        $this->actingAs(instanceStatusUser(), 'api')
            ->getJson('/api/v1/skylogs-instance/status/'.$this->instance->id)
            ->assertSuccessful()
            ->assertExactJson([
                'isConnected' => true,
                'enabled' => false,
                'cluster' => null,
            ]);
    });

    it('reports the instance disconnected and does not request raft status', function () {
        Http::fake([
            'http://instance-status.test' => Http::failedConnection(),
        ]);

        $this->actingAs(instanceStatusUser(), 'api')
            ->getJson('/api/v1/skylogs-instance/status/'.$this->instance->id)
            ->assertSuccessful()
            ->assertExactJson([
                'isConnected' => false,
                'enabled' => null,
                'cluster' => null,
            ]);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/ha/cluster/status'));
    });

    it('keeps the instance connected when the remote raft status is unavailable', function () {
        Http::fake([
            'http://instance-status.test/api/v1/ha/cluster/status' => Http::response(['message' => 'unavailable'], 503),
            'http://instance-status.test' => Http::response(['message' => 'Welcome to Skylogs!']),
        ]);

        $this->actingAs(instanceStatusUser(), 'api')
            ->getJson('/api/v1/skylogs-instance/status/'.$this->instance->id)
            ->assertSuccessful()
            ->assertExactJson([
                'isConnected' => true,
                'enabled' => null,
                'cluster' => null,
            ]);
    });
});
