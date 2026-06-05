<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'SkylogsInstance',
    description: 'Remote Skylogs cluster instances used for health monitoring. CRUD requires owner role; `all` and `status` are available to any authenticated user.'
)]
class SkylogsInstanceDocs
{
    #[OA\Get(
        path: '/api/v1/skylogs-instance',
        operationId: 'getSkylogsInstances',
        summary: 'List Skylogs instances (paginated)',
        description: 'Owner role only.',
        security: [['bearerAuth' => []]],
        tags: ['SkylogsInstance'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'name', description: 'Filter by name (partial match)', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated instances (token hidden)',
                content: new OA\JsonContent(ref: '#/components/schemas/PaginatedSkylogsInstances')
            ),
        ]
    )]
    public function index() {}

    #[OA\Get(
        path: '/api/v1/skylogs-instance/all',
        operationId: 'getAllSkylogsInstances',
        summary: 'List all Skylogs instances',
        security: [['bearerAuth' => []]],
        tags: ['SkylogsInstance'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'All instances without pagination',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/SkylogsInstance'))
            ),
        ]
    )]
    public function all() {}

    #[OA\Get(
        path: '/api/v1/skylogs-instance/status/{id}',
        operationId: 'getSkylogsInstanceConnectionStatus',
        summary: 'Check if instance is connected',
        security: [['bearerAuth' => []]],
        tags: ['SkylogsInstance'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Connection status',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'isConnected', type: 'boolean'),
                    ]
                )
            ),
        ]
    )]
    public function status() {}

    #[OA\Get(
        path: '/api/v1/skylogs-instance/{id}',
        operationId: 'getSkylogsInstance',
        summary: 'Get Skylogs instance by id',
        description: 'Owner role only.',
        security: [['bearerAuth' => []]],
        tags: ['SkylogsInstance'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Instance', content: new OA\JsonContent(ref: '#/components/schemas/SkylogsInstance')),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/skylogs-instance',
        operationId: 'createSkylogsInstance',
        summary: 'Create Skylogs instance',
        description: 'Generates a unique `token` for agent authentication. Owner role only.',
        security: [['bearerAuth' => []]],
        tags: ['SkylogsInstance'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SkylogsInstanceInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SkylogsInstance'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store() {}

    #[OA\Put(
        path: '/api/v1/skylogs-instance/{id}',
        operationId: 'updateSkylogsInstance',
        summary: 'Update Skylogs instance',
        security: [['bearerAuth' => []]],
        tags: ['SkylogsInstance'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SkylogsInstanceInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SkylogsInstance'),
                    ]
                )
            ),
        ]
    )]
    public function update() {}

    #[OA\Delete(
        path: '/api/v1/skylogs-instance/{id}',
        operationId: 'deleteSkylogsInstance',
        summary: 'Delete Skylogs instance',
        description: 'Also removes related health cluster data. Owner role only.',
        security: [['bearerAuth' => []]],
        tags: ['SkylogsInstance'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted instance'),
        ]
    )]
    public function destroy() {}
}

#[OA\Schema(
    schema: 'SkylogsInstance',
    properties: [
        new OA\Property(property: '_id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'type', type: 'string'),
        new OA\Property(property: 'url', type: 'string', example: 'https://remote.example.com'),
        new OA\Property(property: 'token', description: 'Agent auth token (omitted in list/all responses)', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'SkylogsInstanceInput',
    required: ['name', 'type', 'url'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'type', type: 'string'),
        new OA\Property(property: 'url', type: 'string'),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedSkylogsInstances',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SkylogsInstance')),
        new OA\Property(property: 'total', type: 'integer'),
    ]
)]
class SkylogsInstanceSchemasDoc {}
