<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Status',
    description: 'Status pages grouped by tags with aggregated alert counts. Requires owner or manager for CRUD.'
)]
class StatusDocs
{
    #[OA\Get(
        path: '/api/v1/status/all',
        operationId: 'getPublicStatusList',
        summary: 'List all status pages (public)',
        description: 'No JWT required. Used by public status dashboards.',
        tags: ['Status'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'All status records',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/StatusPage'))
            ),
        ]
    )]
    public function statusAll() {}

    #[OA\Get(
        path: '/api/v1/status',
        operationId: 'getStatusPages',
        summary: 'List status pages (paginated)',
        security: [['bearerAuth' => []]],
        tags: ['Status'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'name', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated status pages',
                content: new OA\JsonContent(ref: '#/components/schemas/PaginatedStatusPages')
            ),
        ]
    )]
    public function index() {}

    #[OA\Get(
        path: '/api/v1/status/{id}',
        operationId: 'getStatusPage',
        summary: 'Get status page by id',
        security: [['bearerAuth' => []]],
        tags: ['Status'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status page', content: new OA\JsonContent(ref: '#/components/schemas/StatusPage')),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/status',
        operationId: 'createStatusPage',
        summary: 'Create status page',
        security: [['bearerAuth' => []]],
        tags: ['Status'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StatusPageInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Created',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store() {}

    #[OA\Put(
        path: '/api/v1/status/{id}',
        operationId: 'updateStatusPage',
        summary: 'Update status page',
        security: [['bearerAuth' => []]],
        tags: ['Status'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StatusPageInput')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')),
        ]
    )]
    public function update() {}

    #[OA\Delete(
        path: '/api/v1/status/{id}',
        operationId: 'deleteStatusPage',
        summary: 'Delete status page',
        security: [['bearerAuth' => []]],
        tags: ['Status'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')),
        ]
    )]
    public function destroy() {}
}

#[OA\Schema(
    schema: 'StatusPage',
    properties: [
        new OA\Property(property: '_id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'criticalCount', type: 'integer'),
        new OA\Property(property: 'warningCount', type: 'integer'),
        new OA\Property(property: 'status', ref: '#/components/schemas/AlertRuleState'),
    ]
)]
#[OA\Schema(
    schema: 'StatusPageInput',
    required: ['name', 'tags'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'tags', description: 'Non-empty array of tag names to aggregate alerts', type: 'array', items: new OA\Items(type: 'string')),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedStatusPages',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/StatusPage')),
        new OA\Property(property: 'total', type: 'integer'),
    ]
)]
class StatusSchemasDoc {}
