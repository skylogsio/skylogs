<?php

namespace App\Docs\Config;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'ConfigEmail',
    description: 'Manage email configuration',
)]
class ConfigEmailDoc
{
    // ----------------------------
    // GET /api/v1/config/email
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/config/email',
        operationId: 'getConfigEmailList',
        summary: 'List email configuration',
        security: [['bearerAuth' => []]],
        tags: ['ConfigEmail'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    public function index() {}

    // ----------------------------
    // GET /api/v1/config/email/{id}
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/config/email/{id}',
        operationId: 'getConfigEmail',
        summary: 'Get config by id',
        security: [['bearerAuth' => []]],
        tags: ['ConfigEmail'],
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
    // POST /api/v1/config/email
    // ----------------------------
    #[OA\Post(
        path: '/api/v1/config/email',
        operationId: 'createConfigEmail',
        summary: 'Create email config',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Primary Email'),
                    new OA\Property(property: 'host', type: 'string', example: 'email.skylogs.io'),
                    new OA\Property(property: 'port', type: 'string', example: '80'),
                    new OA\Property(property: 'username', type: 'string', example: 'admin'),
                    new OA\Property(property: 'password', type: 'string', example: '123456789'),
                    new OA\Property(property: 'fromAddress', type: 'string', example: 'Info@skylogs.io'),
                ],
                type: 'object'
            )
        ),
        tags: ['ConfigEmail'],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store() {}

    // ----------------------------
    // PUT /api/v1/config/email/{id}
    // ----------------------------
    #[OA\Put(
        path: '/api/v1/config/email/{id}',
        operationId: 'updateConfigEmail',
        summary: 'Update email config',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Primary Email'),
                    new OA\Property(property: 'host', type: 'string', example: 'email.skylogs.io'),
                    new OA\Property(property: 'port', type: 'string', example: '80'),
                    new OA\Property(property: 'username', type: 'string', example: 'admin'),
                    new OA\Property(property: 'password', type: 'string', example: '123456789'),
                    new OA\Property(property: 'fromAddress', type: 'string', example: 'Info@skylogs.io'),
                ],
                type: 'object'
            )
        ),
        tags: ['ConfigEmail'],
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
    // DELETE /api/v1/config/email/{id}
    // ----------------------------
    #[OA\Delete(
        path: '/api/v1/config/email/{id}',
        operationId: 'deleteConfigEmail',
        summary: 'Delete email config',
        security: [['bearerAuth' => []]],
        tags: ['ConfigEmail'],
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
    // POST /api/v1/config/email/resolve/{id}
    // ----------------------------
    #[OA\Post(
        path: '/api/v1/config/email/resolve/{id}',
        operationId: 'makeDefaultConfigEmail',
        summary: 'make default config email',
        security: [['bearerAuth' => []]],
        tags: ['ConfigEmail'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function makeDefault() {}

    // ----------------------------
    // GET /api/v1/config/email/types
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/config/email/providers',
        operationId: 'getConfigEmailProviders',
        summary: 'List available email config providers',
        tags: ['ConfigEmail'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function getTypes() {}
}
