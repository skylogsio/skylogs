<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'HA',
    description: 'High availability cluster status from the local Raft sidecar. When HA_ENABLED is false the endpoint reports that high availability is disabled and does not call Raft. Requires authentication.'
)]
class HaClusterStatusDocs
{
    #[OA\Get(
        path: '/api/v1/ha/cluster/status',
        operationId: 'getHaClusterStatus',
        summary: 'Get HA Raft cluster status',
        description: 'Returns `enabled` and `cluster`. When HA is enabled, `cluster` is the local Raft sidecar `GET /cluster/status` body (peers, known leader, local state). When `HA_ENABLED` is false, `cluster` is null and Raft is not called.',
        security: [['bearerAuth' => []]],
        tags: ['HA'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cluster status. `cluster` is null when high availability is disabled.',
                content: new OA\JsonContent(ref: '#/components/schemas/HaClusterStatusResponse')
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
    schema: 'HaClusterStatusResponse',
    required: ['enabled', 'cluster'],
    properties: [
        new OA\Property(property: 'enabled', type: 'boolean', example: true),
        new OA\Property(
            property: 'cluster',
            ref: '#/components/schemas/HaClusterStatus',
            description: 'Raft cluster status. Null when high availability is disabled.',
            nullable: true
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
