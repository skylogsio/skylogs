<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'On-Call Plans',
    description: 'One optional weekly on-call plan per team. Create and update send name, timezone, layer delays, and the Excel roster in one request. Each person sets their on-call endpoint on the endpoint itself.'
)]
class OnCallPlanDocs
{
    #[OA\Get(
        path: '/api/v1/team/{teamId}/on-call-plan',
        operationId: 'getTeamOnCallPlan',
        summary: 'Get the team on-call plan',
        security: [['bearerAuth' => []]],
        tags: ['On-Call Plans'],
        parameters: [
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'On-call plan', content: new OA\JsonContent(ref: '#/components/schemas/OnCallPlan')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Team or plan not found'),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/team/{teamId}/on-call-plan',
        operationId: 'createTeamOnCallPlan',
        summary: 'Create the team on-call plan',
        description: 'Multipart: name, timezone, optional layerDelays, and an xlsx file (one sheet per layer, Time + User). Fails with 422 if the team already has a plan. Admin or the team owner.',
        security: [['bearerAuth' => []]],
        tags: ['On-Call Plans'],
        parameters: [
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(ref: '#/components/schemas/OnCallPlanInput')
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/OnCallPlan')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation or Excel parse error'),
        ]
    )]
    public function store() {}

    #[OA\Put(
        path: '/api/v1/team/{teamId}/on-call-plan',
        operationId: 'updateTeamOnCallPlan',
        summary: 'Replace the team on-call plan',
        description: 'Same multipart body as create: name, timezone, optional layerDelays, and the Excel roster.',
        security: [['bearerAuth' => []]],
        tags: ['On-Call Plans'],
        parameters: [
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(ref: '#/components/schemas/OnCallPlanInput')
        )),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/OnCallPlan')),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function update() {}

    #[OA\Delete(
        path: '/api/v1/team/{teamId}/on-call-plan',
        operationId: 'deleteTeamOnCallPlan',
        summary: 'Delete the team on-call plan',
        security: [['bearerAuth' => []]],
        tags: ['On-Call Plans'],
        parameters: [
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function destroy() {}

    #[OA\Get(
        path: '/api/v1/team/{teamId}/on-call-plan/at',
        operationId: 'getTeamOnCallAt',
        summary: 'Who is on call for this team at a time',
        security: [['bearerAuth' => []]],
        tags: ['On-Call Plans'],
        parameters: [
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'at', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Per-layer on-call', content: new OA\JsonContent(ref: '#/components/schemas/OnCallAt')),
            new OA\Response(response: 404, description: 'No plan'),
        ]
    )]
    public function at() {}

    #[OA\Get(
        path: '/api/v1/on-call-plan/current',
        operationId: 'getCurrentOnCall',
        summary: 'Who is on call across teams',
        security: [['bearerAuth' => []]],
        tags: ['On-Call Plans'],
        parameters: [
            new OA\Parameter(name: 'at', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'teamIds[]', in: 'query', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string'))),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'One block per team. `plan` is null when the team has no plan.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/OnCallCurrent')),
                    ]
                )
            ),
        ]
    )]
    public function current() {}
}

#[OA\Schema(
    schema: 'OnCallPlan',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'teamId', type: 'string'),
        new OA\Property(property: 'team', properties: [
            new OA\Property(property: 'id', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
        ], type: 'object', nullable: true),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'timezone', type: 'string'),
        new OA\Property(property: 'layers', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'roster', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'isComplete', type: 'boolean'),
        new OA\Property(property: 'canEdit', type: 'boolean'),
        new OA\Property(property: 'canDelete', type: 'boolean'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
class OnCallPlanSchema {}

#[OA\Schema(
    schema: 'OnCallPlanInput',
    required: ['name', 'timezone', 'file'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'timezone', type: 'string', example: 'Asia/Tehran'),
        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'xlsx, one sheet per layer, Time and User columns'),
        new OA\Property(property: 'layerDelays', type: 'array', items: new OA\Items(type: 'integer'), description: 'Minutes to wait after each layer, in sheet order'),
    ]
)]
class OnCallPlanInputSchema {}

#[OA\Schema(
    schema: 'OnCallAt',
    properties: [
        new OA\Property(property: 'at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'timezone', type: 'string'),
        new OA\Property(property: 'teamId', type: 'string'),
        new OA\Property(property: 'plan', properties: [
            new OA\Property(property: 'id', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
        ], type: 'object'),
        new OA\Property(property: 'layers', type: 'array', items: new OA\Items(type: 'object')),
    ]
)]
class OnCallAtSchema {}

#[OA\Schema(
    schema: 'OnCallCurrent',
    properties: [
        new OA\Property(property: 'teamId', type: 'string'),
        new OA\Property(property: 'teamName', type: 'string'),
        new OA\Property(property: 'plan', type: 'object', nullable: true),
        new OA\Property(property: 'at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'timezone', type: 'string', nullable: true),
        new OA\Property(property: 'layers', type: 'array', items: new OA\Items(type: 'object')),
    ]
)]
class OnCallCurrentSchema {}
