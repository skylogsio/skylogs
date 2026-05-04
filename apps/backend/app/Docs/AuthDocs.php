<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Authentication',
    description: 'JWT-based authentication endpoints for Skylogs API'
)]
class AuthDocs
{
    #[OA\Post(
        path: '/api/v1/auth/login',
        operationId: 'loginUser',
        summary: 'Authenticate user and retrieve access token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'admin'),
                    new OA\Property(property: 'password', type: 'string', example: '123456'),
                ]
            )
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully authenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'accessToken', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJI...'),
                        new OA\Property(property: 'refreshToken', type: 'string', example: 'eyJhbGciOiJIUzI1NiIsInR5...'),
                        new OA\Property(property: 'tokenType', type: 'string', example: 'bearer'),
                        new OA\Property(
                            property: 'roles',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['admin', 'user']
                        ), new OA\Property(property: 'expiresIn', type: 'integer', example: 3600),
                        new OA\Property(property: 'refreshExpiresIn', type: 'integer', example: 10800),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials'),
        ]
    )]
    public function login() {}

    #[OA\Post(
        path: '/api/v1/auth/me',
        operationId: 'getCurrentUser',
        summary: 'Get authenticated user info',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(response: 200, description: 'Authenticated user', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJI...'),
                    new OA\Property(property: 'username', type: 'string', example: 'eyJhbGciOiJIUzI1NiIsInR5...'),
                    new OA\Property(property: 'createdAt', type: 'string', example: '2025-09-22T19:03:16.922+00:00'),
                    new OA\Property(property: 'updatedAt', type: 'string', example: '2025-09-22T19:03:16.922+00:00'),
                    new OA\Property(
                        property: 'roles',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['owner']
                    ),
                    new OA\Property(
                        property: 'permissions',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                    ), new OA\Property(property: 'expiresIn', type: 'integer', example: 3600),
                ]
            )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function me() {}

    #[OA\Post(
        path: '/api/v1/auth/pass',
        operationId: 'changePasswordCurrentUser',
        summary: 'Change the authenticated user password',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['currentPassword', 'newPassword', 'confirmPassword'],
                properties: [
                    new OA\Property(property: 'currentPassword', type: 'string', format: 'password', example: 'OldPassword123!'),
                    new OA\Property(property: 'newPassword', type: 'string', format: 'password', example: 'NewPassword123!'),
                    new OA\Property(property: 'confirmPassword', type: 'string', format: 'password', example: 'NewPassword123!'),
                ]
            )
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password changed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', example: '{"_id": "507f1f77bcf86cd799439011", "name": "John Doe", "email": "john@example.com"}'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error or incorrect current password',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Current password is incorrect'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function pass() {}

    #[OA\Post(
        path: '/api/v1/auth/logout',
        operationId: 'logoutUser',
        summary: 'Logout user and invalidate token',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully logged out',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Successfully logged out'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function logout() {}

    #[OA\Post(
        path: '/api/v1/auth/refresh',
        operationId: 'refreshToken',
        summary: 'Refresh JWT access token',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token refreshed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'accessToken', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJI...'),
                        new OA\Property(property: 'refreshToken', type: 'string', example: 'eyJhbGciOiJIUzI1NiIsInR5...'),
                        new OA\Property(property: 'tokenType', type: 'string', example: 'bearer'),
                        new OA\Property(
                            property: 'roles',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['owner']
                        ), new OA\Property(property: 'expiresIn', type: 'integer', example: 3600),
                        new OA\Property(property: 'refreshExpiresIn', type: 'integer', example: 10800),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Token invalid or expired'),
        ]
    )]
    public function refresh() {}
}
