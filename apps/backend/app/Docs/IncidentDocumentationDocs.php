<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Incident Documentation',
    description: 'Everything written about an incident: the postmortem and its root cause analysis, the timeline, '.
        'attached evidence, and the follow-up action items. Read access follows the incident, and writing requires '.
        'edit rights on it.'
)]
class IncidentDocumentationDocs
{
    #[OA\Get(
        path: '/api/v1/incident/{incidentId}/postmortem',
        operationId: 'getIncidentPostMortem',
        summary: 'Get the postmortem of an incident',
        description: 'Returns `data: null` while no postmortem has been written.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The postmortem, or null',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', nullable: true, ref: '#/components/schemas/PostMortem'),
                ])
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function showPostMortem() {}

    #[OA\Put(
        path: '/api/v1/incident/{incidentId}/postmortem',
        operationId: 'upsertIncidentPostMortem',
        summary: 'Create or replace the postmortem of an incident',
        description: 'An incident holds at most one postmortem, so this endpoint creates it on the first call and '.
            'replaces it afterwards. Omitted fields are cleared. Requires edit rights on the incident.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PostMortemInput')),
        responses: [
            new OA\Response(response: 200, description: 'Saved', content: new OA\JsonContent(ref: '#/components/schemas/PostMortem')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updatePostMortem() {}

    #[OA\Post(
        path: '/api/v1/incident/{incidentId}/postmortem/publish',
        operationId: 'publishIncidentPostMortem',
        summary: 'Publish the postmortem of an incident',
        description: 'Moves the postmortem to `published`, stamps `publishedAt` on the first publish, and records a '.
            'timeline entry. Requires edit rights on the incident.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Published', content: new OA\JsonContent(ref: '#/components/schemas/PostMortem')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'No postmortem to publish'),
        ]
    )]
    public function publishPostMortem() {}

    #[OA\Get(
        path: '/api/v1/incident/{incidentId}/timeline',
        operationId: 'getIncidentTimeline',
        summary: 'List timeline entries of an incident (paginated)',
        description: 'Ordered by `occurredAt` ascending, so the list reads as the story of the incident.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 100)),
            new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/IncidentTimelineEntryType')),
            new OA\Parameter(name: 'source', in: 'query', schema: new OA\Schema(type: 'string', enum: ['system', 'user', 'alert', 'webhook'])),
            new OA\Parameter(name: 'isPublic', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated timeline entries',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/IncidentTimelineEntry')),
                ])
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function indexTimeline() {}

    #[OA\Post(
        path: '/api/v1/incident/{incidentId}/timeline',
        operationId: 'createIncidentTimelineEntry',
        summary: 'Add a timeline entry to an incident',
        description: 'Only the responder written types are accepted here; `created`, `acknowledged`, `statusChanged`, '.
            '`resolved`, `updated` and `postMortemPublished` are written by the application itself. Requires edit '.
            'rights on the incident.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/IncidentTimelineEntryInput')),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/IncidentTimelineEntry')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function storeTimelineEntry() {}

    #[OA\Get(
        path: '/api/v1/incident/{incidentId}/document',
        operationId: 'getIncidentDocuments',
        summary: 'List documents attached to an incident (paginated)',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['screenshot', 'log', 'metric', 'diagram', 'report', 'other'])),
            new OA\Parameter(name: 'attachableType', in: 'query', schema: new OA\Schema(type: 'string', enum: ['incident', 'postMortem'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated documents',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/IncidentDocument')),
                ])
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function indexDocuments() {}

    #[OA\Post(
        path: '/api/v1/incident/{incidentId}/document',
        operationId: 'createIncidentDocument',
        summary: 'Attach a file or an external link to an incident',
        description: 'Send exactly one of `file` (multipart, up to 20 MB) or `externalUrl`. Requires edit rights on '.
            'the incident. Attaching to `postMortem` is only possible once the postmortem exists.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(
                                property: 'file',
                                description: 'Images, PDF, plain text, CSV, Markdown, YAML, JSON, zip or gzip, up to 20 MB',
                                type: 'string',
                                format: 'binary'
                            ),
                            new OA\Property(property: 'name', type: 'string', description: 'Defaults to the uploaded file name'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'type', type: 'string', enum: ['screenshot', 'log', 'metric', 'diagram', 'report', 'other'], default: 'other'),
                            new OA\Property(property: 'attachableType', type: 'string', enum: ['incident', 'postMortem'], default: 'incident'),
                        ]
                    )
                ),
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['externalUrl'],
                        properties: [
                            new OA\Property(property: 'externalUrl', type: 'string', description: 'A link to evidence held elsewhere, such as a dashboard'),
                            new OA\Property(property: 'name', type: 'string', description: 'Defaults to the URL'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'type', type: 'string', enum: ['screenshot', 'log', 'metric', 'diagram', 'report', 'other'], default: 'other'),
                            new OA\Property(property: 'attachableType', type: 'string', enum: ['incident', 'postMortem'], default: 'incident'),
                        ]
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 201, description: 'Attached', content: new OA\JsonContent(ref: '#/components/schemas/IncidentDocument')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function storeDocument() {}

    #[OA\Get(
        path: '/api/v1/incident/{incidentId}/document/{documentId}/download-url',
        operationId: 'getIncidentDocumentDownloadUrl',
        summary: 'Get a download URL for a document',
        description: 'Uploads are served through a signed URL that expires after 10 minutes, so the browser can fetch '.
            'the file without carrying the bearer token. External links are returned unchanged with a null expiry.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'documentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Download target',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'url', type: 'string'),
                    new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', nullable: true),
                ])
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function documentDownloadUrl() {}

    #[OA\Get(
        path: '/api/v1/incident-document/{documentId}/download',
        operationId: 'downloadIncidentDocument',
        summary: 'Download an uploaded document',
        description: 'Authorised by the signature in the query string rather than a bearer token. Call '.
            '`/download-url` to obtain a valid link.',
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'documentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The file',
                content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary'))
            ),
            new OA\Response(response: 403, description: 'Missing or expired signature'),
            new OA\Response(response: 404, description: 'Not Found, or the document is an external link'),
        ]
    )]
    public function downloadDocument() {}

    #[OA\Delete(
        path: '/api/v1/incident/{incidentId}/document/{documentId}',
        operationId: 'deleteIncidentDocument',
        summary: 'Delete a document and its stored file',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'documentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(properties: [new OA\Property(property: 'status', type: 'boolean')])),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function destroyDocument() {}

    #[OA\Get(
        path: '/api/v1/incident/{incidentId}/action-item',
        operationId: 'getIncidentActionItems',
        summary: 'List action items of an incident (paginated)',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 50)),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['open', 'inProgress', 'blocked', 'done', 'cancelled'])),
            new OA\Parameter(name: 'priority', in: 'query', schema: new OA\Schema(type: 'string', enum: ['low', 'medium', 'high', 'critical'])),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string', enum: ['prevention', 'detection', 'mitigation', 'process', 'documentation', 'other'])),
            new OA\Parameter(name: 'ownerId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'teamId', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated action items',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/IncidentActionItem')),
                ])
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function indexActionItems() {}

    #[OA\Post(
        path: '/api/v1/incident/{incidentId}/action-item',
        operationId: 'createIncidentActionItem',
        summary: 'Add an action item to an incident',
        description: 'Requires edit rights on the incident. `postMortemId`, when given, must be the postmortem of '.
            'this incident.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/IncidentActionItemInput')),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/IncidentActionItem')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function storeActionItem() {}

    #[OA\Put(
        path: '/api/v1/incident/{incidentId}/action-item/{actionItemId}',
        operationId: 'updateIncidentActionItem',
        summary: 'Update an action item',
        description: '`completedAt` is stamped when the item first reaches `done` and cleared when it is reopened.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'actionItemId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/IncidentActionItemInput')),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/IncidentActionItem')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateActionItem() {}

    #[OA\Delete(
        path: '/api/v1/incident/{incidentId}/action-item/{actionItemId}',
        operationId: 'deleteIncidentActionItem',
        summary: 'Delete an action item',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'incidentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'actionItemId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(properties: [new OA\Property(property: 'status', type: 'boolean')])),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function destroyActionItem() {}

    #[OA\Get(
        path: '/api/v1/incident-action-item',
        operationId: 'getActionItems',
        summary: 'List action items across incidents (paginated)',
        description: 'The follow-up backlog: items the caller owns or created, plus items assigned to one of their '.
            'teams. Each entry carries a short summary of its incident.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Documentation'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['open', 'inProgress', 'blocked', 'done', 'cancelled'])),
            new OA\Parameter(name: 'priority', in: 'query', schema: new OA\Schema(type: 'string', enum: ['low', 'medium', 'high', 'critical'])),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string', enum: ['prevention', 'detection', 'mitigation', 'process', 'documentation', 'other'])),
            new OA\Parameter(name: 'ownerId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'teamId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'incidentId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'open', in: 'query', description: 'Only items that are not done or cancelled', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'overdue', in: 'query', description: 'Only unfinished items past their due date', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Matches the action item title', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated action items',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/IncidentActionItem')),
                ])
            ),
        ]
    )]
    public function indexAllActionItems() {}
}

#[OA\Schema(
    schema: 'PostMortem',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'incidentId', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'inReview', 'approved', 'published']),
        new OA\Property(property: 'summary', type: 'string'),
        new OA\Property(property: 'impact', type: 'string', nullable: true),
        new OA\Property(property: 'detection', type: 'string', nullable: true, description: 'How the incident was noticed'),
        new OA\Property(property: 'resolution', type: 'string', nullable: true),
        new OA\Property(property: 'rootCause', ref: '#/components/schemas/PostMortemRootCause'),
        new OA\Property(property: 'whatWentWell', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'whatWentWrong', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'lessonsLearned', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'authorId', type: 'string', nullable: true),
        new OA\Property(property: 'authorUser', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
        ]),
        new OA\Property(property: 'reviewerIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'dueAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'publishedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'canEdit', type: 'boolean'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
class PostMortemSchema {}

#[OA\Schema(
    schema: 'PostMortemInput',
    required: ['summary'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'inReview', 'approved', 'published'], default: 'draft'),
        new OA\Property(property: 'summary', type: 'string'),
        new OA\Property(property: 'impact', type: 'string', nullable: true),
        new OA\Property(property: 'detection', type: 'string', nullable: true),
        new OA\Property(property: 'resolution', type: 'string', nullable: true),
        new OA\Property(property: 'rootCause', ref: '#/components/schemas/PostMortemRootCause'),
        new OA\Property(property: 'whatWentWell', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'whatWentWrong', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'lessonsLearned', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'authorId', type: 'string', nullable: true, description: 'Defaults to the caller on the first write'),
        new OA\Property(property: 'reviewerIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'dueAt', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class PostMortemInputSchema {}

#[OA\Schema(
    schema: 'PostMortemRootCause',
    description: 'The root cause analysis, embedded in the postmortem.',
    properties: [
        new OA\Property(property: 'method', type: 'string', enum: ['fiveWhys', 'fishbone', 'timeline', 'other'], nullable: true),
        new OA\Property(property: 'whys', type: 'array', description: 'Up to 10 successive answers', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'contributingFactors', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'statement', type: 'string', nullable: true, description: 'The conclusion, in one paragraph'),
    ],
    type: 'object'
)]
class PostMortemRootCauseSchema {}

#[OA\Schema(
    schema: 'IncidentTimelineEntryType',
    description: 'System written: created, updated, acknowledged, statusChanged, resolved, postMortemPublished. '.
        'Responder written: note, action, detection, mitigation, escalation, communication.',
    type: 'string',
    enum: [
        'created', 'updated', 'acknowledged', 'statusChanged', 'resolved', 'postMortemPublished',
        'note', 'action', 'detection', 'mitigation', 'escalation', 'communication',
    ]
)]
class IncidentTimelineEntryTypeSchema {}

#[OA\Schema(
    schema: 'IncidentTimelineEntry',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'incidentId', type: 'string'),
        new OA\Property(property: 'type', ref: '#/components/schemas/IncidentTimelineEntryType'),
        new OA\Property(property: 'source', type: 'string', enum: ['system', 'user', 'alert', 'webhook']),
        new OA\Property(property: 'occurredAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'meta', type: 'object', description: 'Structured detail, such as the from and to of a status change'),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the entry may be shown outside the response team'),
        new OA\Property(property: 'userId', type: 'string', nullable: true),
        new OA\Property(property: 'user', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
        ]),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
class IncidentTimelineEntrySchema {}

#[OA\Schema(
    schema: 'IncidentTimelineEntryInput',
    required: ['type', 'message'],
    properties: [
        new OA\Property(
            property: 'type',
            type: 'string',
            enum: ['note', 'action', 'detection', 'mitigation', 'escalation', 'communication']
        ),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'occurredAt', type: 'string', format: 'date-time', nullable: true, description: 'Defaults to now. May be backdated.'),
        new OA\Property(property: 'meta', type: 'object', nullable: true),
        new OA\Property(property: 'isPublic', type: 'boolean', default: false),
    ]
)]
class IncidentTimelineEntryInputSchema {}

#[OA\Schema(
    schema: 'IncidentDocument',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'incidentId', type: 'string'),
        new OA\Property(property: 'attachableType', type: 'string', enum: ['incident', 'postMortem']),
        new OA\Property(property: 'attachableId', type: 'string'),
        new OA\Property(property: 'type', type: 'string', enum: ['screenshot', 'log', 'metric', 'diagram', 'report', 'other']),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'fileName', type: 'string', nullable: true, description: 'Null for an external link'),
        new OA\Property(property: 'mimeType', type: 'string', nullable: true),
        new OA\Property(property: 'size', type: 'integer', nullable: true, description: 'Bytes'),
        new OA\Property(property: 'externalUrl', type: 'string', nullable: true),
        new OA\Property(property: 'isExternalLink', type: 'boolean'),
        new OA\Property(property: 'uploadedBy', type: 'string', nullable: true),
        new OA\Property(property: 'uploadedByUser', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
        ]),
        new OA\Property(property: 'canDelete', type: 'boolean'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
class IncidentDocumentSchema {}

#[OA\Schema(
    schema: 'IncidentActionItem',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'incidentId', type: 'string'),
        new OA\Property(
            property: 'incident',
            description: 'Present on the cross-incident listing',
            properties: [
                new OA\Property(property: 'id', type: 'string'),
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'severity', type: 'string'),
                new OA\Property(property: 'status', type: 'string'),
            ],
            type: 'object',
            nullable: true
        ),
        new OA\Property(property: 'postMortemId', type: 'string', nullable: true),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'ownerId', type: 'string', nullable: true),
        new OA\Property(property: 'ownerUser', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
        ]),
        new OA\Property(property: 'teamId', type: 'string', nullable: true),
        new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high', 'critical']),
        new OA\Property(property: 'category', type: 'string', enum: ['prevention', 'detection', 'mitigation', 'process', 'documentation', 'other']),
        new OA\Property(property: 'status', type: 'string', enum: ['open', 'inProgress', 'blocked', 'done', 'cancelled']),
        new OA\Property(property: 'dueAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'completedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'createdBy', type: 'string', nullable: true),
        new OA\Property(property: 'canEdit', type: 'boolean'),
        new OA\Property(property: 'canDelete', type: 'boolean'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
class IncidentActionItemSchema {}

#[OA\Schema(
    schema: 'IncidentActionItemInput',
    required: ['title'],
    properties: [
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'ownerId', type: 'string', nullable: true),
        new OA\Property(property: 'teamId', type: 'string', nullable: true),
        new OA\Property(property: 'postMortemId', type: 'string', nullable: true, description: 'Must be the postmortem of the same incident'),
        new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high', 'critical'], default: 'medium'),
        new OA\Property(property: 'category', type: 'string', enum: ['prevention', 'detection', 'mitigation', 'process', 'documentation', 'other'], default: 'other'),
        new OA\Property(property: 'status', type: 'string', enum: ['open', 'inProgress', 'blocked', 'done', 'cancelled'], default: 'open'),
        new OA\Property(property: 'dueAt', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class IncidentActionItemInputSchema {}
