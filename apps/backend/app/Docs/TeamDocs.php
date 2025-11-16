<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Teams',
    description: 'Manage Teams'
)]
class TeamDocs
{
    #[OA\Get(
        path: '/api/v1/team',
        operationId: 'getTeams',
        summary: 'Get list of teams',
        security: [['bearerAuth' => []]],
        tags: ['Teams'],
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
            new OA\Parameter(
                name: 'name',
                in: 'query',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of teams',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Team')
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index() {}

    #[OA\Get(
        path: '/api/v1/team/{id}',
        operationId: 'getTeam',
        summary: 'Get team by ID',
        security: [['bearerAuth' => []]],
        tags: ['Data Sources'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Team details',
                content: new OA\JsonContent(ref: '#/components/schemas/Team')
            ),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/team',
        operationId: 'createTeam',
        summary: 'Create new team',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'type', 'url'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Customer Service'),
                    new OA\Property(property: 'ownerId', type: 'string'),
                    new OA\Property(property: 'userIds', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'description', type: 'string'),
                ]
            )
        ),
        tags: ['Data Sources'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Team created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Team'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function create() {}

    #[OA\Put(
        path: '/api/v1/team/{id}',
        operationId: 'updateTeam',
        summary: 'Update team',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/TeamInput')
        ),
        tags: ['Data Sources'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Team updated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update() {}

    #[OA\Delete(
        path: '/api/v1/team/{id}',
        operationId: 'deleteTeam',
        summary: 'Delete team',
        security: [['bearerAuth' => []]],
        tags: ['Data Sources'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Team deleted'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete() {}
}

#[OA\Schema(
    schema: 'Team',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'ownerId', type: 'string'),
        new OA\Property(property: 'userIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
class TeamSchema {}

#[OA\Schema(
    schema: 'TeamInput',
    required: ['name', 'type', 'url'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'ownerId', type: 'string'),
        new OA\Property(property: 'userIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'description', type: 'string'),
    ]
)]
class TeamInputSchema {}
