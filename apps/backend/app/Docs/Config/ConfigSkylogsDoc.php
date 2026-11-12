<?php

namespace App\Docs\Config;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'ConfigSkylogs',
    description: 'Skylogs cluster mode: main server or agent linked to a parent. Owner role only.'
)]
class ConfigSkylogsDoc
{
    #[OA\Get(
        path: '/api/v1/config/skylogs/cluster',
        operationId: 'getSkylogsClusterConfig',
        summary: 'Get cluster configuration',
        description: 'Returns saved config or defaults (`type: main`, empty source URL/token).',
        security: [['bearerAuth' => []]],
        tags: ['ConfigSkylogs'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cluster config',
                content: new OA\JsonContent(ref: '#/components/schemas/SkylogsClusterConfig')
            ),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/config/skylogs/cluster',
        operationId: 'storeSkylogsClusterConfig',
        summary: 'Save cluster configuration',
        security: [['bearerAuth' => []]],
        tags: ['ConfigSkylogs'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SkylogsClusterConfigInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Saved config',
                content: new OA\JsonContent(ref: '#/components/schemas/SkylogsClusterConfig')
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store() {}
}

#[OA\Schema(
    schema: 'SkylogsClusterConfig',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'cluster'),
        new OA\Property(property: 'type', type: 'string', enum: ['main', 'agent']),
        new OA\Property(property: 'sourceUrl', description: 'Parent Skylogs URL when type is agent', type: 'string'),
        new OA\Property(property: 'sourceToken', description: 'Parent cluster token when type is agent', type: 'string'),
    ]
)]
#[OA\Schema(
    schema: 'SkylogsClusterConfigInput',
    required: ['type'],
    properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['main', 'agent']),
        new OA\Property(property: 'sourceUrl', description: 'Required when type is agent', type: 'string', format: 'uri'),
        new OA\Property(property: 'sourceToken', description: 'Required when type is agent', type: 'string'),
    ]
)]
class ConfigSkylogsSchemasDoc {}
