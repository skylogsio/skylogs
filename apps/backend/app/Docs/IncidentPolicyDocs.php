<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Incident Policies',
    description: 'Incident response policies. Definitions can be applied from the YAML DSL (apiVersion '.
        'skylogs.io/v1) or written as JSON. Policies are configuration only; nothing acts on them yet.'
)]
class IncidentPolicyDocs
{
    #[OA\Get(
        path: '/api/v1/incident-policy',
        operationId: 'getIncidentPolicies',
        summary: 'List incident policies (paginated)',
        security: [['bearerAuth' => []]],
        tags: ['Incident Policies'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'enabled', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'teamId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Matches the policy name', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated incident policies',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'current_page', type: 'integer'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/IncidentPolicy')),
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
        path: '/api/v1/incident-policy/{id}',
        operationId: 'getIncidentPolicy',
        summary: 'Get an incident policy by ID',
        security: [['bearerAuth' => []]],
        tags: ['Incident Policies'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Policy details', content: new OA\JsonContent(ref: '#/components/schemas/IncidentPolicy')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/incident-policy',
        operationId: 'createIncidentPolicy',
        summary: 'Create an incident policy from JSON',
        description: 'The JSON alternative to the YAML import, for a form driven editor. The body mirrors the stored '.
            'shape and the policy is recorded with `source: api`. Requires the owner or manager role.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Policies'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/IncidentPolicyInput')),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/IncidentPolicy')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store() {}

    #[OA\Put(
        path: '/api/v1/incident-policy/{id}',
        operationId: 'updateIncidentPolicy',
        summary: 'Update an incident policy from JSON',
        description: 'Replaces the definition and bumps the version. Requires the owner or manager role.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Policies'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/IncidentPolicyInput')),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/IncidentPolicy')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update() {}

    #[OA\Post(
        path: '/api/v1/incident-policy/import',
        operationId: 'importIncidentPolicy',
        summary: 'Apply one or more policies from a YAML definition',
        description: 'Send a multipart file upload or a raw definition in the `yaml` field. Idempotent by policy name: '.
            'unchanged definitions are reported as unchanged, changed ones bump the version. Nothing is written unless '.
            'every document in the input is valid. Requires the owner or manager role.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Policies'],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'A .yaml or .yml file, up to 512 KB'),
                            new OA\Property(property: 'dryRun', type: 'boolean', default: false),
                        ]
                    )
                ),
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['yaml'],
                        properties: [
                            new OA\Property(property: 'yaml', type: 'string', description: 'The YAML definition, may contain several documents separated by ---'),
                            new OA\Property(property: 'dryRun', type: 'boolean', default: false),
                        ]
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 200, description: 'Applied', content: new OA\JsonContent(ref: '#/components/schemas/IncidentPolicyImportResult')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Invalid definition', content: new OA\JsonContent(ref: '#/components/schemas/IncidentPolicyImportResult')),
        ]
    )]
    public function import() {}

    #[OA\Post(
        path: '/api/v1/incident-policy/validate',
        operationId: 'validateIncidentPolicy',
        summary: 'Validate a YAML definition without writing anything',
        description: 'Runs the same structural, semantic and reference checks as import. Suitable for gating a CI pipeline.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Policies'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['yaml'],
                properties: [
                    new OA\Property(property: 'yaml', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Valid', content: new OA\JsonContent(ref: '#/components/schemas/IncidentPolicyImportResult')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Invalid definition', content: new OA\JsonContent(ref: '#/components/schemas/IncidentPolicyImportResult')),
        ]
    )]
    public function validateImport() {}

    #[OA\Get(
        path: '/api/v1/incident-policy/{id}/export',
        operationId: 'exportIncidentPolicy',
        summary: 'Export a policy back to the YAML DSL',
        description: 'Ids are rendered as names so the output can be committed and reviewed.',
        security: [['bearerAuth' => []]],
        tags: ['Incident Policies'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'YAML definition',
                content: new OA\MediaType(mediaType: 'application/x-yaml', schema: new OA\Schema(type: 'string'))
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function export() {}

    #[OA\Delete(
        path: '/api/v1/incident-policy/{id}',
        operationId: 'deleteIncidentPolicy',
        summary: 'Delete an incident policy',
        security: [['bearerAuth' => []]],
        tags: ['Incident Policies'],
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
    schema: 'IncidentPolicy',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'name', type: 'string', description: 'Slug shaped, unique, and the key used for idempotent imports'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'enabled', type: 'boolean'),
        new OA\Property(property: 'version', type: 'integer', description: 'Incremented on every import that changes the definition'),
        new OA\Property(property: 'source', type: 'string', enum: ['yaml', 'api']),
        new OA\Property(property: 'ownerId', type: 'string', nullable: true),
        new OA\Property(property: 'teamIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(
            property: 'teams',
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'string'),
                new OA\Property(property: 'name', type: 'string'),
            ])
        ),
        new OA\Property(
            property: 'match',
            description: 'Which alerts fall under this policy',
            properties: [
                new OA\Property(property: 'alertRuleIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'serviceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceTypes', type: 'array', items: new OA\Items(type: 'string')),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'grouping',
            description: 'How an alert storm collapses into one incident',
            properties: [
                new OA\Property(property: 'key', type: 'array', items: new OA\Items(type: 'string', enum: ['serviceId', 'alertRuleId', 'tag', 'dataSourceType'])),
                new OA\Property(property: 'windowMinutes', type: 'integer'),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'incident',
            properties: [
                new OA\Property(property: 'autoCreate', type: 'boolean'),
                new OA\Property(property: 'autoResolveOnAlertClear', type: 'boolean'),
                new OA\Property(property: 'titleTemplate', type: 'string', nullable: true),
                new OA\Property(property: 'defaultSeverity', type: 'string', enum: ['SEV1', 'SEV2', 'SEV3', 'SEV4']),
                new OA\Property(property: 'severityMap', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string')),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'rules',
            description: 'Per severity response rules, keyed by SEV1 to SEV4',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(ref: '#/components/schemas/IncidentPolicyRule')
        ),
        new OA\Property(property: 'createdBy', type: 'string'),
        new OA\Property(property: 'updatedBy', type: 'string', nullable: true),
        new OA\Property(property: 'canEdit', type: 'boolean'),
        new OA\Property(property: 'canDelete', type: 'boolean'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
class IncidentPolicySchema {}

#[OA\Schema(
    schema: 'IncidentPolicyRule',
    properties: [
        new OA\Property(property: 'ackWithinMinutes', type: 'integer', nullable: true),
        new OA\Property(property: 'resolveWithinMinutes', type: 'integer', nullable: true),
        new OA\Property(property: 'requireCommander', type: 'boolean'),
        new OA\Property(property: 'notifyEndpointIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'escalation', properties: [
            new OA\Property(property: 'onCallPlanId', type: 'string', nullable: true),
            new OA\Property(property: 'useLayers', type: 'boolean'),
        ], type: 'object'),
        new OA\Property(property: 'communication', properties: [
            new OA\Property(property: 'stakeholderUpdateEveryMinutes', type: 'integer', nullable: true),
            new OA\Property(property: 'statusPageUpdateRequired', type: 'boolean'),
        ], type: 'object'),
        new OA\Property(property: 'postmortem', properties: [
            new OA\Property(property: 'required', type: 'boolean'),
            new OA\Property(property: 'dueDays', type: 'integer', nullable: true),
            new OA\Property(property: 'reviewRequired', type: 'boolean'),
        ], type: 'object'),
        new OA\Property(
            property: 'runbookNames',
            type: 'array',
            description: 'Runbook names as written in the definition, kept even when no runbook carries that name yet.',
            items: new OA\Items(type: 'string')
        ),
        new OA\Property(
            property: 'runbookIds',
            type: 'array',
            description: 'Read only. The ids of the runbooks that the names above resolved to.',
            items: new OA\Items(type: 'string')
        ),
    ]
)]
class IncidentPolicyRuleSchema {}

#[OA\Schema(
    schema: 'IncidentPolicyInput',
    description: 'The JSON body of a policy create or update. Mirrors the stored document: references are ids and '.
        'rules are keyed by severity.',
    required: ['name', 'teamIds', 'match', 'rules'],
    properties: [
        new OA\Property(property: 'name', type: 'string', description: 'Lowercase, dash separated, and unique'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'enabled', type: 'boolean', default: true),
        new OA\Property(property: 'ownerId', type: 'string', nullable: true),
        new OA\Property(property: 'teamIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(
            property: 'match',
            description: 'At least one matcher is required, otherwise the policy would cover every alert.',
            properties: [
                new OA\Property(property: 'alertRuleIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'serviceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceTypes', type: 'array', items: new OA\Items(type: 'string')),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'grouping',
            properties: [
                new OA\Property(property: 'key', type: 'array', items: new OA\Items(type: 'string', enum: ['serviceId', 'alertRuleId', 'tag', 'dataSourceType'])),
                new OA\Property(property: 'windowMinutes', type: 'integer', description: '1 to 1440'),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'incident',
            properties: [
                new OA\Property(property: 'autoCreate', type: 'boolean'),
                new OA\Property(property: 'autoResolveOnAlertClear', type: 'boolean'),
                new OA\Property(property: 'titleTemplate', type: 'string', nullable: true),
                new OA\Property(property: 'defaultSeverity', type: 'string', enum: ['SEV1', 'SEV2', 'SEV3', 'SEV4']),
                new OA\Property(property: 'severityMap', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string')),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'rules',
            description: 'Keyed by SEV1 to SEV4, at least one entry. `runbookIds` is ignored on input.',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(ref: '#/components/schemas/IncidentPolicyRule')
        ),
    ]
)]
class IncidentPolicyInputSchema {}

#[OA\Schema(
    schema: 'IncidentPolicyImportResult',
    properties: [
        new OA\Property(property: 'valid', type: 'boolean'),
        new OA\Property(property: 'dryRun', type: 'boolean'),
        new OA\Property(property: 'created', type: 'array', items: new OA\Items(ref: '#/components/schemas/IncidentPolicyImportEntry')),
        new OA\Property(property: 'updated', type: 'array', items: new OA\Items(ref: '#/components/schemas/IncidentPolicyImportEntry')),
        new OA\Property(property: 'unchanged', type: 'array', items: new OA\Items(ref: '#/components/schemas/IncidentPolicyImportEntry')),
        new OA\Property(
            property: 'errors',
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'path', type: 'string', example: 'spec.rules[0].notify.channels[1]'),
                new OA\Property(property: 'message', type: 'string', example: "Endpoint 'oncall-sms' not found."),
            ])
        ),
    ]
)]
class IncidentPolicyImportResultSchema {}

#[OA\Schema(
    schema: 'IncidentPolicyImportEntry',
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'id', type: 'string', nullable: true, description: 'Null on a dry run for policies that do not exist yet'),
        new OA\Property(property: 'version', type: 'integer'),
    ]
)]
class IncidentPolicyImportEntrySchema {}
