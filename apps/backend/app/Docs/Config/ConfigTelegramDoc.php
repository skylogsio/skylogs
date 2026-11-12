<?php

namespace App\Docs\Config;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'ConfigTelegram',
    description: 'Telegram bot proxy configuration (HTTP/SOCKS5). Owner role only. Only one config can be active at a time.'
)]
class ConfigTelegramDoc
{
    #[OA\Get(
        path: '/api/v1/config/telegram',
        operationId: 'getConfigTelegramList',
        summary: 'List Telegram proxy configs',
        description: 'Ordered with active config first.',
        security: [['bearerAuth' => []]],
        tags: ['ConfigTelegram'],
        parameters: [
            new OA\Parameter(name: 'name', description: 'Filter by name', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Telegram configs',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ConfigTelegram'))
            ),
        ]
    )]
    public function index() {}

    #[OA\Get(
        path: '/api/v1/config/telegram/{id}',
        operationId: 'getConfigTelegram',
        summary: 'Get Telegram config by id',
        security: [['bearerAuth' => []]],
        tags: ['ConfigTelegram'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ConfigTelegram')),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/config/telegram',
        operationId: 'createConfigTelegram',
        summary: 'Create Telegram proxy config',
        security: [['bearerAuth' => []]],
        tags: ['ConfigTelegram'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ConfigTelegramInput')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Created (inactive until activated)'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store() {}

    #[OA\Put(
        path: '/api/v1/config/telegram/{id}',
        operationId: 'updateConfigTelegram',
        summary: 'Update Telegram proxy config',
        security: [['bearerAuth' => []]],
        tags: ['ConfigTelegram'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ConfigTelegramInput')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated'),
        ]
    )]
    public function update() {}

    #[OA\Delete(
        path: '/api/v1/config/telegram/{id}',
        operationId: 'deleteConfigTelegram',
        summary: 'Delete Telegram proxy config',
        security: [['bearerAuth' => []]],
        tags: ['ConfigTelegram'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
        ]
    )]
    public function destroy() {}

    #[OA\Post(
        path: '/api/v1/config/telegram/activate/{id}',
        operationId: 'activateConfigTelegram',
        summary: 'Activate a Telegram config',
        description: 'Deactivates any other active config.',
        security: [['bearerAuth' => []]],
        tags: ['ConfigTelegram'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Activated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/ConfigTelegram'),
                    ]
                )
            ),
        ]
    )]
    public function activate() {}

    #[OA\Post(
        path: '/api/v1/config/telegram/deactivate',
        operationId: 'deactivateConfigTelegram',
        summary: 'Deactivate current Telegram config',
        security: [['bearerAuth' => []]],
        tags: ['ConfigTelegram'],
        responses: [
            new OA\Response(response: 200, description: 'Deactivated', content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')),
        ]
    )]
    public function deactivate() {}
}

#[OA\Schema(
    schema: 'ConfigTelegram',
    properties: [
        new OA\Property(property: '_id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'type', type: 'string', enum: ['http', 'socks5']),
        new OA\Property(property: 'host', type: 'string'),
        new OA\Property(property: 'port', type: 'string'),
        new OA\Property(property: 'username', type: 'string'),
        new OA\Property(property: 'password', type: 'string'),
        new OA\Property(property: 'active', type: 'boolean'),
    ]
)]
#[OA\Schema(
    schema: 'ConfigTelegramInput',
    required: ['name', 'type', 'host', 'port'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'type', type: 'string', enum: ['http', 'socks5']),
        new OA\Property(property: 'host', type: 'string'),
        new OA\Property(property: 'port', type: 'string'),
        new OA\Property(property: 'username', type: 'string'),
        new OA\Property(property: 'password', type: 'string'),
    ]
)]
class ConfigTelegramSchemasDoc {}
