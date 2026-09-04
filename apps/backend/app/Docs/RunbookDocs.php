<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Runbooks',
    description: 'Operational procedures teams follow during an incident. Referenced by incident policies through runbookNames.'
)]
class RunbookDocs
{
    #[OA\Get(
        path: '/api/v1/runbook',
        operationId: 'getRunbooks',
        summary: 'List runbooks (paginated)',
        description: 'Scoped to the caller\'s teams, plus runbooks that are not assigned to any team.',
        security: [['bearerAuth' => []]],
        tags: ['Runbooks'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['draft', 'published', 'archived'])),
            new OA\Parameter(name: 'teamId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tag', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Matches the runbook name', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated runbooks',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'current_page', type: 'integer'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Runbook')),
                        new OA\Property(property: 'last_page', type: 'integer'),
                        new OA\Property(property: 'per_page', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer'),
                    ]
                )
            ),
        ]
    )]
    public function index() {}

    #[OA\Get(
        path: '/api/v1/runbook/{id}',
        operationId: 'getRunbook',
        summary: 'Get a runbook by ID',
        security: [['bearerAuth' => []]],
        tags: ['Runbooks'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Runbook details', content: new OA\JsonContent(ref: '#/components/schemas/Runbook')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/runbook',
        operationId: 'createRunbook',
        summary: 'Create a runbook',
        description: 'Requires the owner or manager role. The slug is derived from the name when omitted.',
        security: [['bearerAuth' => []]],
        tags: ['Runbooks'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RunbookInput')),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/Runbook')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store() {}

    #[OA\Put(
        path: '/api/v1/runbook/{id}',
        operationId: 'updateRunbook',
        summary: 'Update a runbook',
        description: 'Requires the owner or manager role. Every write bumps the version.',
        security: [['bearerAuth' => []]],
        tags: ['Runbooks'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RunbookInput')),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/Runbook')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update() {}

    #[OA\Delete(
        path: '/api/v1/runbook/{id}',
        operationId: 'deleteRunbook',
        summary: 'Delete a runbook',
        security: [['bearerAuth' => []]],
        tags: ['Runbooks'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(properties: [new OA\Property(property: 'status', type: 'boolean')])),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function destroy() {}
}

#[OA\Schema(
    schema: 'Runbook',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'slug', type: 'string', description: 'Unique, lowercase, dash separated'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published', 'archived']),
        new OA\Property(property: 'version', type: 'integer', description: 'Incremented on every update'),
        new OA\Property(property: 'sourceType', type: 'string', enum: ['steps', 'markdown', 'externalUrl']),
        new OA\Property(property: 'content', type: 'string', nullable: true, description: 'Markdown body, set when sourceType is markdown'),
        new OA\Property(property: 'externalUrl', type: 'string', nullable: true, description: 'Set when sourceType is externalUrl'),
        new OA\Property(property: 'steps', type: 'array', items: new OA\Items(ref: '#/components/schemas/RunbookStep')),
        new OA\Property(property: 'teamIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(
            property: 'teams',
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'string'),
                new OA\Property(property: 'name', type: 'string'),
            ])
        ),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'appliesTo', ref: '#/components/schemas/RunbookAppliesTo'),
        new OA\Property(property: 'reviewIntervalDays', type: 'integer', nullable: true, description: 'How often the runbook should be reviewed'),
        new OA\Property(property: 'createdBy', type: 'string'),
        new OA\Property(property: 'updatedBy', type: 'string', nullable: true),
        new OA\Property(property: 'canEdit', type: 'boolean'),
        new OA\Property(property: 'canDelete', type: 'boolean'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
class RunbookSchema {}

#[OA\Schema(
    schema: 'RunbookInput',
    required: ['name', 'teamIds', 'sourceType'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'slug', type: 'string', nullable: true, description: 'Derived from the name when omitted'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'teamIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published', 'archived'], default: 'draft'),
        new OA\Property(property: 'sourceType', type: 'string', enum: ['steps', 'markdown', 'externalUrl']),
        new OA\Property(property: 'content', type: 'string', nullable: true, description: 'Required when sourceType is markdown'),
        new OA\Property(property: 'externalUrl', type: 'string', nullable: true, description: 'Required when sourceType is externalUrl'),
        new OA\Property(
            property: 'steps',
            type: 'array',
            description: 'Required when sourceType is steps',
            items: new OA\Items(ref: '#/components/schemas/RunbookStep')
        ),
        new OA\Property(property: 'appliesTo', ref: '#/components/schemas/RunbookAppliesTo'),
        new OA\Property(property: 'reviewIntervalDays', type: 'integer', nullable: true),
    ]
)]
class RunbookInputSchema {}

#[OA\Schema(
    schema: 'RunbookStep',
    required: ['title'],
    properties: [
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'command', type: 'string', nullable: true),
        new OA\Property(property: 'expectedResult', type: 'string', nullable: true),
    ]
)]
class RunbookStepSchema {}

#[OA\Schema(
    schema: 'RunbookAppliesTo',
    description: 'Where the runbook is relevant. Advisory only, nothing is matched automatically yet.',
    properties: [
        new OA\Property(property: 'serviceIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'alertRuleIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'severities', type: 'array', items: new OA\Items(type: 'string', enum: ['SEV1', 'SEV2', 'SEV3', 'SEV4'])),
    ],
    type: 'object'
)]
class RunbookAppliesToSchema {}
