<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'HA',
    description: 'High availability cluster status from the local Raft sidecar. Requires authentication.'
)]
class HaClusterStatusDocs
{
    #[OA\Get(
        path: '/api/v1/ha/cluster/status',
        operationId: 'getHaClusterStatus',
        summary: 'Get HA Raft cluster status',
        description: 'Proxies the local Raft sidecar `GET /cluster/status` response (peers, known leader, local state).',
        security: [['bearerAuth' => []]],
        tags: ['HA'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Raft cluster status',
                content: new OA\JsonContent(ref: '#/components/schemas/HaClusterStatus')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(
                response: 503,
                description: 'Raft sidecar unavailable',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
        ]
    )]
    public function show() {}
}

#[OA\Schema(
    schema: 'HaClusterStatus',
    properties: [
        new OA\Property(property: 'reporter_id', type: 'string', example: 'node2'),
        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
        new OA\Property(property: 'local_state', type: 'string', example: 'Leader'),
        new OA\Property(property: 'known_leader_id', type: 'string', example: 'node2'),
        new OA\Property(property: 'known_leader_address', type: 'string', example: '172.28.7.12:7000'),
        new OA\Property(
            property: 'peers',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/HaClusterPeer')
        ),
    ]
)]
#[OA\Schema(
    schema: 'HaClusterPeer',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'node1'),
        new OA\Property(property: 'address', type: 'string', example: '172.28.7.11:7000'),
        new OA\Property(property: 'suffrage', type: 'string', example: 'Voter'),
        new OA\Property(property: 'tcp_reachable', type: 'boolean'),
    ]
)]
class HaClusterStatusSchemasDoc {}
