<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Users',
    description: 'User accounts and roles (owner, manager, member). Manager/owner routes require elevated roles.'
)]
class UserDocs
{
    #[OA\Get(
        path: '/api/v1/user',
        operationId: 'getUsers',
        summary: 'List users (paginated)',
        description: 'Requires owner or manager role.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'username', description: 'Filter by username (partial match)', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated users; each item includes `roles` string array',
                content: new OA\JsonContent(ref: '#/components/schemas/PaginatedUsers')
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index() {}

    #[OA\Get(
        path: '/api/v1/user/all',
        operationId: 'getAllUsers',
        summary: 'List all users',
        description: 'Unpaginated list for dropdowns and sharing UI.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'All users',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/User'))
            ),
        ]
    )]
    public function all() {}

    #[OA\Get(
        path: '/api/v1/user/{id}',
        operationId: 'getUser',
        summary: 'Get user by id',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User', content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/user',
        operationId: 'createUser',
        summary: 'Create user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UserInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store() {}

    #[OA\Put(
        path: '/api/v1/user/{id}',
        operationId: 'updateUser',
        summary: 'Update user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'name', 'role'],
                properties: [
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'role', type: 'string', enum: ['owner', 'manager', 'member']),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function update() {}

    #[OA\Put(
        path: '/api/v1/user/pass/{id}',
        operationId: 'changeUserPassword',
        summary: 'Change user password (admin)',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password', 'confirmPassword'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'confirmPassword', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password updated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function changePassword() {}

    #[OA\Delete(
        path: '/api/v1/user/{id}',
        operationId: 'deleteUser',
        summary: 'Delete user',
        description: 'Reassigns owned endpoints and alert rules to the system admin user. Cannot delete `admin`.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted user record'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function destroy() {}

    #[OA\Post(
        path: '/api/v1/user/changeOwner',
        operationId: 'changeUserOwnership',
        summary: 'Transfer alert rules and endpoints between users',
        description: 'Owner role only. Moves all alert rules and endpoints from `fromUser` to `toUser`.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['fromUser', 'toUser'],
                properties: [
                    new OA\Property(property: 'fromUser', description: 'Source user id', type: 'string'),
                    new OA\Property(property: 'toUser', description: 'Target user id', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ownership transferred',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function changeOwner() {}
}

#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: '_id', type: 'string'),
        new OA\Property(property: 'username', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string', enum: ['owner', 'manager', 'member'])),
    ]
)]
#[OA\Schema(
    schema: 'UserInput',
    required: ['username', 'name', 'password', 'confirmPassword', 'role'],
    properties: [
        new OA\Property(property: 'username', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'password', type: 'string', format: 'password'),
        new OA\Property(property: 'confirmPassword', type: 'string', format: 'password'),
        new OA\Property(property: 'role', type: 'string', enum: ['owner', 'manager', 'member']),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedUsers',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
        new OA\Property(property: 'last_page', type: 'integer'),
        new OA\Property(property: 'per_page', type: 'integer'),
        new OA\Property(property: 'total', type: 'integer'),
    ]
)]
class UserSchemasDoc {}
