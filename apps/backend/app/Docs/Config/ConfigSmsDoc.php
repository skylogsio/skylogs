<?php

namespace App\Docs\Config;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'ConfigSms',
    description: 'Manage sms configuration',
)]
class ConfigSmsDoc
{
    // ----------------------------
    // GET /api/v1/config/sms
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/config/sms',
        operationId: 'getConfigSmsList',
        summary: 'List sms configuration',
        security: [['bearerAuth' => []]],
        tags: ['ConfigSms'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    public function index() {}

    // ----------------------------
    // GET /api/v1/config/sms/{id}
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/config/sms/{id}',
        operationId: 'getConfigSms',
        summary: 'Get config by id',
        security: [['bearerAuth' => []]],
        tags: ['ConfigSms'],
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
    // POST /api/v1/config/sms
    // ----------------------------
    #[OA\Post(
        path: '/api/v1/config/sms',
        operationId: 'createConfigSms',
        summary: 'Create sms config',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                discriminator: new OA\Discriminator(
                    propertyName: 'provider',
                    mapping: [
                        'kaveNegar' => '#/components/schemas/KaveNegarConfigSMS',
                    ]
                ),
                oneOf: [
                    new OA\Schema(ref: '#/components/schemas/KaveNegarConfigSMS'),
                ]
            )
        ),
        tags: ['ConfigSms'],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store() {}

    // ----------------------------
    // PUT /api/v1/config/sms/{id}
    // ----------------------------
    #[OA\Put(
        path: '/api/v1/config/sms/{id}',
        operationId: 'updateConfigSms',
        summary: 'Update sms config',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                discriminator: new OA\Discriminator(
                    propertyName: 'provider',
                    mapping: [
                        'kaveNegar' => '#/components/schemas/KaveNegarConfigSMS',
                    ]
                ),
                oneOf: [
                    new OA\Schema(ref: '#/components/schemas/KaveNegarConfigSMS'),
                ]
            )
        ),
        tags: ['ConfigSms'],
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
    // DELETE /api/v1/config/sms/{id}
    // ----------------------------
    #[OA\Delete(
        path: '/api/v1/config/sms/{id}',
        operationId: 'deleteConfigSms',
        summary: 'Delete sms config',
        security: [['bearerAuth' => []]],
        tags: ['ConfigSms'],
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
    // POST /api/v1/config/sms/resolve/{id}
    // ----------------------------
    #[OA\Post(
        path: '/api/v1/config/sms/resolve/{id}',
        operationId: 'makeDefaultConfigSms',
        summary: 'make default config sms',
        security: [['bearerAuth' => []]],
        tags: ['ConfigSms'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function makeDefault() {}

    // ----------------------------
    // GET /api/v1/config/sms/types
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/config/sms/providers',
        operationId: 'getConfigSmsProviders',
        summary: 'List available sms config providers',
        tags: ['ConfigSms'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function getTypes() {}
}

#[OA\Schema(
    schema: 'KaveNegarConfigSMS',
    title: 'Kave Negar SMS Config',
    required: ['name', 'provider', 'apiToken', 'senderNumber'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Primary SMS'),
        new OA\Property(property: 'provider', type: 'string', enum: ['kaveNegar'], example: 'kaveNegar'),
        new OA\Property(property: 'apiToken', type: 'string', example: 'your-api-token-here'),
        new OA\Property(property: 'senderNumber', type: 'string', example: '10008000800'),
    ]
)]
class SmsConfigSchemas {}
