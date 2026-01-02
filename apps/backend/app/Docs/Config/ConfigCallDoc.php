<?php

namespace App\Docs\Config;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'ConfigCall',
    description: 'Manage call configuration',
)]
class ConfigCallDoc
{
    // ----------------------------
    // GET /api/v1/config/call
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/config/call',
        operationId: 'getConfigCallList',
        summary: 'List call configuration',
        security: [['bearerAuth' => []]],
        tags: ['ConfigCall'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'perPage',
                in: 'query',
                schema: new OA\Schema(type: 'integer', default: 25)
            ),

        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    public function index() {}

    // ----------------------------
    // GET /api/v1/config/call/{id}
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/config/call/{id}',
        operationId: 'getConfigCall',
        summary: 'Get config by id',
        security: [['bearerAuth' => []]],
        tags: ['ConfigCall'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: '64b59f1a...')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show() {}

    // ----------------------------
    // POST /api/v1/config/call
    // ----------------------------
    #[OA\Post(
        path: '/api/v1/config/call',
        operationId: 'createConfigCall',
        summary: 'Create call config',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                discriminator: new OA\Discriminator(
                    propertyName: 'provider',
                    mapping: [
                        'kaveNegar' => '#/components/schemas/KaveNegarConfig',
                    ]
                ),
                oneOf: [
                    new OA\Schema(ref: '#/components/schemas/KaveNegarConfig'),
                ]
            )
        ),
        tags: ['ConfigCall'],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store() {}

    // ----------------------------
    // PUT /api/v1/config/call/{id}
    // ----------------------------
    #[OA\Put(
        path: '/api/v1/config/call/{id}',
        operationId: 'updateConfigCall',
        summary: 'Update call config',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                discriminator: new OA\Discriminator(
                    propertyName: 'provider',
                    mapping: [
                        'kaveNegar' => '#/components/schemas/KaveNegarConfig',
                    ]
                ),
                oneOf: [
                    new OA\Schema(ref: '#/components/schemas/KaveNegarConfig'),
                ]
            )
        ),
        tags: ['ConfigCall'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: '64b59f1a...')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function update() {}

    // ----------------------------
    // DELETE /api/v1/config/call/{id}
    // ----------------------------
    #[OA\Delete(
        path: '/api/v1/config/call/{id}',
        operationId: 'deleteConfigCall',
        summary: 'Delete call config',
        security: [['bearerAuth' => []]],
        tags: ['ConfigCall'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: '64b59f1a...')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function destroy() {}

    // ----------------------------
    // POST /api/v1/config/call/resolve/{id}
    // ----------------------------
    #[OA\Post(
        path: '/api/v1/config/call/resolve/{id}',
        operationId: 'makeDefaultConfigCall',
        summary: 'make default config call',
        security: [['bearerAuth' => []]],
        tags: ['ConfigCall'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function makeDefault() {}

    // ----------------------------
    // GET /api/v1/config/call/types
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/config/call/providers',
        operationId: 'getConfigCallProviders',
        summary: 'List available call config providers',
        tags: ['ConfigCall'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function getTypes() {}
}

#[OA\Schema(
    schema: 'KaveNegarConfig',
    title: 'Kave Negar Call Config',
    required: ['name', 'provider', 'apiToken', 'senderNumber'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Primary Call'),
        new OA\Property(property: 'provider', type: 'string', enum: ['kaveNegar'], example: 'kaveNegar'),
        new OA\Property(property: 'apiToken', type: 'string', example: 'your-api-token-here'),
    ]
)]
class CallConfigSchemas {}
