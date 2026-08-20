<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Incidents',
    description: 'Incident management: CRUD, acknowledge, and resolve. The postmortem, timeline, documents and '.
        'action items of an incident live under the Incident Documentation tag.'
)]
class IncidentDocs
{
    #[OA\Get(
        path: '/api/v1/incident',
        operationId: 'getIncidents',
        summary: 'List incidents (paginated)',
        security: [['bearerAuth' => []]],
        tags: ['Incidents'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['open', 'investigating', 'resolved'])),
            new OA\Parameter(name: 'severity', in: 'query', schema: new OA\Schema(type: 'string', enum: ['SEV1', 'SEV2', 'SEV3', 'SEV4'])),
            new OA\Parameter(name: 'teamId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tag', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated incidents',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Incident')),
                    ]
                )
            ),
        ]
    )]
    public function index() {}

    #[OA\Get(
        path: '/api/v1/incident/{id}',
        operationId: 'getIncident',
        summary: 'Get incident by ID',
        security: [['bearerAuth' => []]],
        tags: ['Incidents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Incident details', content: new OA\JsonContent(ref: '#/components/schemas/Incident')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/incident',
        operationId: 'createIncident',
        summary: 'Create a new incident (manual)',
        security: [['bearerAuth' => []]],
        tags: ['Incidents'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/IncidentInput')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/Incident')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store() {}

    #[OA\Put(
        path: '/api/v1/incident/{id}',
        operationId: 'updateIncident',
        summary: 'Update incident details',
        security: [['bearerAuth' => []]],
        tags: ['Incidents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/IncidentInput')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/Incident')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update() {}

    #[OA\Delete(
        path: '/api/v1/incident/{id}',
        operationId: 'deleteIncident',
        summary: 'Delete an incident',
        security: [['bearerAuth' => []]],
        tags: ['Incidents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(properties: [new OA\Property(property: 'status', type: 'boolean')])),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function destroy() {}

    #[OA\Post(
        path: '/api/v1/incident/{id}/acknowledge',
        operationId: 'acknowledgeIncident',
        summary: 'Acknowledge an incident for one or more teams (OPEN -> INVESTIGATING)',
        security: [['bearerAuth' => []]],
        tags: ['Incidents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'teamId', type: 'string', nullable: true, description: 'Team to acknowledge for. When omitted, acknowledges all of the caller\'s assigned teams that have not yet acknowledged.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Acknowledged', content: new OA\JsonContent(ref: '#/components/schemas/Incident')),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function acknowledge() {}

    #[OA\Post(
        path: '/api/v1/incident/{id}/resolve',
        operationId: 'resolveIncident',
        summary: 'Resolve an incident (-> RESOLVED)',
        security: [['bearerAuth' => []]],
        tags: ['Incidents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'resolvedAt', type: 'string', format: 'date-time', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Resolved', content: new OA\JsonContent(ref: '#/components/schemas/Incident')),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function resolve() {}
}

#[OA\Schema(
    schema: 'Incident',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'severity', type: 'string', enum: ['SEV1', 'SEV2', 'SEV3', 'SEV4']),
        new OA\Property(property: 'status', type: 'string', enum: ['open', 'investigating', 'resolved']),
        new OA\Property(property: 'source', type: 'string', enum: ['manual', 'policy']),
        new OA\Property(property: 'startedAt', type: 'string', format: 'date-time', description: 'When the incident occurred / started'),
        new OA\Property(property: 'detectedAt', type: 'string', format: 'date-time', description: 'When the incident was detected'),
        new OA\Property(property: 'resolvedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'teamIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'alertRuleIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'createdBy', type: 'string'),
        new OA\Property(property: 'resolvedBy', type: 'string', nullable: true),
        new OA\Property(
            property: 'acknowledgements',
            type: 'array',
            description: 'Per-team acknowledgements',
            items: new OA\Items(properties: [
                new OA\Property(property: 'teamId', type: 'string'),
                new OA\Property(property: 'acknowledgedBy', type: 'string'),
                new OA\Property(property: 'acknowledgedAt', type: 'string', format: 'date-time'),
            ])
        ),
        new OA\Property(
            property: 'teams',
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'string'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'onCallPlan', type: 'object', nullable: true, properties: [
                    new OA\Property(property: 'id', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                ]),
                new OA\Property(property: 'acknowledgement', type: 'object', nullable: true, properties: [
                    new OA\Property(property: 'acknowledgedBy', type: 'string'),
                    new OA\Property(property: 'acknowledgedAt', type: 'string', format: 'date-time'),
                ]),
            ])
        ),
        new OA\Property(
            property: 'alertRules',
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'string'),
                new OA\Property(property: 'name', type: 'string'),
            ])
        ),
        new OA\Property(
            property: 'postMortem',
            description: 'A summary of the postmortem, enough for a badge and a link. The document itself is served by GET /api/v1/incident/{id}/postmortem.',
            properties: [
                new OA\Property(property: 'id', type: 'string'),
                new OA\Property(property: 'status', type: 'string', enum: ['draft', 'inReview', 'approved', 'published']),
                new OA\Property(property: 'authorId', type: 'string', nullable: true),
                new OA\Property(property: 'dueAt', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'publishedAt', type: 'string', format: 'date-time', nullable: true),
            ],
            type: 'object',
            nullable: true
        ),
        new OA\Property(
            property: 'counts',
            description: 'How much documentation the incident carries. Only present on show, null on the list.',
            properties: [
                new OA\Property(property: 'timelineEntries', type: 'integer'),
                new OA\Property(property: 'documents', type: 'integer'),
                new OA\Property(property: 'actionItems', type: 'integer'),
                new OA\Property(property: 'openActionItems', type: 'integer'),
            ],
            type: 'object',
            nullable: true
        ),
        new OA\Property(property: 'canEdit', type: 'boolean'),
        new OA\Property(property: 'canDelete', type: 'boolean'),
        new OA\Property(property: 'canAcknowledge', type: 'boolean'),
        new OA\Property(property: 'canResolve', type: 'boolean'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
class IncidentSchema {}

#[OA\Schema(
    schema: 'IncidentInput',
    required: ['title', 'teamIds', 'severity'],
    properties: [
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'teamIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'startedAt', type: 'string', format: 'date-time', nullable: true, description: 'Occurred / started at. Defaults to now.'),
        new OA\Property(property: 'detectedAt', type: 'string', format: 'date-time', nullable: true, description: 'Detected at. Defaults to now.'),
        new OA\Property(property: 'resolvedAt', type: 'string', format: 'date-time', nullable: true, description: 'Optional. When set on create, the incident is created as resolved.'),
        new OA\Property(property: 'alertRuleIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'severity', type: 'string', enum: ['SEV1', 'SEV2', 'SEV3', 'SEV4']),
    ]
)]
class IncidentInputSchema {}
