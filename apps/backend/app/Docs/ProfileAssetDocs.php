<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'ProfileAsset',
    description: 'Profile assets: bundled alert-rule templates applied for a user. Owner role only.'
)]
class ProfileAssetDocs
{
    #[OA\Get(
        path: '/api/v1/profile/asset',
        operationId: 'getProfileAssets',
        summary: 'List profile assets (paginated)',
        security: [['bearerAuth' => []]],
        tags: ['ProfileAsset'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'name', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated profile assets with `user` relation',
                content: new OA\JsonContent(ref: '#/components/schemas/PaginatedProfileAssets')
            ),
        ]
    )]
    public function index() {}

    #[OA\Get(
        path: '/api/v1/profile/asset/{id}',
        operationId: 'getProfileAsset',
        summary: 'Get profile asset by id',
        security: [['bearerAuth' => []]],
        tags: ['ProfileAsset'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Profile asset', content: new OA\JsonContent(ref: '#/components/schemas/ProfileAsset')),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/profile/asset',
        operationId: 'createProfileAsset',
        summary: 'Create profile asset and provision alert rules',
        description: 'Creates alert rules from `config` via ProfileService and stores their ids in `createdAlertRuleIds`.',
        security: [['bearerAuth' => []]],
        tags: ['ProfileAsset'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProfileAssetInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/ProfileAsset'),
                    ]
                )
            ),
        ]
    )]
    public function store() {}

    #[OA\Put(
        path: '/api/v1/profile/asset/{id}',
        operationId: 'updateProfileAsset',
        summary: 'Update profile asset',
        security: [['bearerAuth' => []]],
        tags: ['ProfileAsset'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProfileAssetInput')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated'),
        ]
    )]
    public function update() {}

    #[OA\Delete(
        path: '/api/v1/profile/asset/{id}',
        operationId: 'deleteProfileAsset',
        summary: 'Delete profile asset',
        description: 'Deletes linked alert rules via ProfileService.',
        security: [['bearerAuth' => []]],
        tags: ['ProfileAsset'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted asset'),
        ]
    )]
    public function destroy() {}
}

#[OA\Schema(
    schema: 'ProfileAsset',
    properties: [
        new OA\Property(property: '_id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'ownerId', type: 'string'),
        new OA\Property(property: 'config', description: 'Profile template configuration object', type: 'object'),
        new OA\Property(property: 'createdAlertRuleIds', type: 'array', items: new OA\Items(type: 'string')),
    ]
)]
#[OA\Schema(
    schema: 'ProfileAssetInput',
    required: ['name', 'ownerId', 'config'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'ownerId', type: 'string'),
        new OA\Property(property: 'config', type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedProfileAssets',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProfileAsset')),
        new OA\Property(property: 'total', type: 'integer'),
    ]
)]
class ProfileAssetSchemasDoc {}
