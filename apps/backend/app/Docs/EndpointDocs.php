<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Endpoints',
    description: 'Manage notification endpoints (SMS, Email, Telegram, Teams, etc.)'
)]
class EndpointDocs
{
    #[OA\Get(
        path: '/api/v1/endpoint',
        operationId: 'getEndpoints',
        summary: 'Get list of endpoints',
        security: [['bearerAuth' => []]],
        tags: ['Endpoints'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'perPage',
                description: 'Items per page',
                in: 'query',
                schema: new OA\Schema(type: 'integer', default: 25)
            ),
            new OA\Parameter(
                name: 'name',
                description: 'Filter by name',
                in: 'query',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of endpoints',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Endpoint')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index() {}

    #[OA\Get(
        path: '/api/v1/endpoint/indexFlow',
        operationId: 'getFlowEndpoints',
        summary: 'Get list of flow endpoints',
        security: [['bearerAuth' => []]],
        tags: ['Endpoints'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of flow endpoints',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Endpoint')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function indexFlow() {}

    #[OA\Get(
        path: '/api/v1/endpoint/createFlowEndpoints',
        operationId: 'getEndpointsForFlow',
        summary: 'Get endpoints available for flow creation',
        security: [['bearerAuth' => []]],
        tags: ['Endpoints'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of available endpoints',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Endpoint')
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function createFlowEndpoints() {}

    #[OA\Get(
        path: '/api/v1/endpoint/{id}',
        operationId: 'getEndpoint',
        summary: 'Get endpoint by ID',
        security: [['bearerAuth' => []]],
        tags: ['Endpoints'],
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
                description: 'Endpoint details. Response structure varies based on endpoint type (sms, email, telegram, flow, etc.)',
                content: new OA\JsonContent(ref: '#/components/schemas/Endpoint')
            ),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/endpoint/sendOTP',
        operationId: 'sendOTP',
        summary: 'send OTP code',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'value'],
                properties: [
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        enum: ['sms', 'call', 'email'],
                        example: 'sms'
                    ),
                    new OA\Property(property: 'value', type: 'string', example: '09000000000'),

                ]
            )
        ),
        tags: ['Endpoints'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'OTP code has been sent successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'OTP code has been sent to your endpoint'),
                        new OA\Property(property: 'expiredAt', type: 'integer', example: 1762022993),
                        new OA\Property(property: 'timeLeft', type: 'integer', example: 180),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function SendOTPCode() {}

    #[OA\Post(
        path: '/api/v1/endpoint',
        operationId: 'createEndpoint',
        summary: 'Create new endpoint',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'type', 'value'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'My SMS Endpoint'),
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        enum: ['sms', 'call', 'email', 'telegram', 'teams', 'matter-most', 'flow'],
                        example: 'sms'
                    ),
                    new OA\Property(property: 'value', type: 'string', example: '09000000000'),
                    new OA\Property(
                        property: 'otpCode',
                        description: 'For sms, call, email type the otp verification is required',
                        type: 'string',
                        example: '12345'
                    ),
                    new OA\Property(property: 'chatId', description: 'For Telegram type', type: 'string'),
                    new OA\Property(property: 'threadId', description: 'For Telegram type', type: 'string'),
                    new OA\Property(property: 'botToken', description: 'For Telegram type', type: 'string'),
                    new OA\Property(property: 'isPublic', type: 'boolean', default: false),
                    new OA\Property(
                        property: 'steps',
                        description: 'For flow type - array of steps with wait and endpoint types',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(
                                    property: 'type',
                                    description: 'Step type - wait for delay or endpoint for notification',
                                    type: 'string',
                                    enum: ['wait', 'endpoint']
                                ),
                                new OA\Property(
                                    property: 'duration',
                                    description: 'Duration for wait type steps',
                                    type: 'integer'
                                ),
                                new OA\Property(
                                    property: 'timeUnit',
                                    description: 'Time unit for wait type steps (seconds, minutes, hours)',
                                    type: 'string',
                                    enum: ['s', 'm', 'h']
                                ),
                                new OA\Property(
                                    property: 'endpointIds',
                                    description: 'Array of endpoint IDs for endpoint type steps',
                                    type: 'array',
                                    items: new OA\Items(type: 'string')
                                ),
                            ]
                        )
                    ),
                ]
            )
        ),
        tags: ['Endpoints'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Endpoint created successfully. Response structure varies based on endpoint type.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            ref: '#/components/schemas/Endpoint'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function create() {}

    #[OA\Put(
        path: '/api/v1/endpoint/{id}',
        operationId: 'updateEndpoint',
        summary: 'Update endpoint',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'type', 'value'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Updated Endpoint Name'),
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        enum: ['sms', 'call', 'email', 'telegram', 'teams', 'matter-most', 'flow'],
                        example: 'telegram'
                    ),
                    new OA\Property(property: 'value', type: 'string', example: '09000000000'),
                    new OA\Property(
                        property: 'otpCode',
                        description: 'For sms, call, email type the otp verification is required if the value updated in process',
                        type: 'string',
                        example: '12345'
                    ),
                    new OA\Property(property: 'chatId', description: 'For Telegram type', type: 'string'),
                    new OA\Property(property: 'threadId', description: 'For Telegram type', type: 'string'),
                    new OA\Property(property: 'botToken', description: 'For Telegram type', type: 'string'),
                    new OA\Property(property: 'isPublic', type: 'boolean', default: false),
                    new OA\Property(
                        property: 'steps',
                        description: 'For flow type - array of steps with wait and endpoint types',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(
                                    property: 'type',
                                    description: 'Step type - wait for delay or endpoint for notification',
                                    type: 'string',
                                    enum: ['wait', 'endpoint']
                                ),
                                new OA\Property(
                                    property: 'duration',
                                    description: 'Duration for wait type steps',
                                    type: 'integer'
                                ),
                                new OA\Property(
                                    property: 'timeUnit',
                                    description: 'Time unit for wait type steps (seconds, minutes, hours)',
                                    type: 'string',
                                    enum: ['s', 'm', 'h']
                                ),
                                new OA\Property(
                                    property: 'endpointIds',
                                    description: 'Array of endpoint IDs for endpoint type steps',
                                    type: 'array',
                                    items: new OA\Items(type: 'string')
                                ),
                            ]
                        )
                    ),
                ]
            )
        ),
        tags: ['Endpoints'],
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
                description: 'Endpoint updated successfully. Response structure varies based on endpoint type.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            ref: '#/components/schemas/Endpoint'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update() {}

    #[OA\Post(
        path: '/api/v1/endpoint/changeOwner/{id}',
        operationId: 'changeEndpointOwner',
        summary: 'Change endpoint owner',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['userId'],
                properties: [
                    new OA\Property(property: 'userId', type: 'string', example: 'user123'),
                ]
            )
        ),
        tags: ['Endpoints'],
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
                description: 'Owner changed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Successfully change owner'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function changeOwner() {}

    #[OA\Delete(
        path: '/api/v1/endpoint/{id}',
        operationId: 'deleteEndpoint',
        summary: 'Delete endpoint',
        security: [['bearerAuth' => []]],
        tags: ['Endpoints'],
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
                description: 'Endpoint deleted successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/Endpoint')
            ),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete() {}
}

#[OA\Schema(
    schema: 'Endpoint',
    description: 'Endpoint model with different structures based on type. The structure varies depending on the endpoint type (sms, email, telegram, flow, etc.)',
    properties: [
        new OA\Property(property: 'id', description: 'Unique identifier for the endpoint', type: 'string'),
        new OA\Property(property: 'name', description: 'Display name for the endpoint', type: 'string'),
        new OA\Property(
            property: 'type',
            description: 'Type of the endpoint',
            type: 'string',
            enum: ['sms', 'call', 'email', 'telegram', 'teams', 'matter-most', 'flow']
        ),
        new OA\Property(property: 'value', description: 'Primary value (phone number, email address, etc.) - used for most types except telegram and flow', type: 'string'),
        new OA\Property(property: 'chatId', description: 'Chat ID for telegram type endpoints', type: 'string'),
        new OA\Property(property: 'threadId', description: 'Thread ID for telegram type endpoints', type: 'string'),
        new OA\Property(property: 'botToken', description: 'Bot token for telegram type endpoints', type: 'string'),
        new OA\Property(
            property: 'steps',
            description: 'Steps array for flow type endpoints',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(
                        property: 'type',
                        description: 'Step type - wait for delay or endpoint for notification',
                        type: 'string',
                        enum: ['wait', 'endpoint']
                    ),
                    new OA\Property(
                        property: 'duration',
                        description: 'Duration for wait type steps',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'timeUnit',
                        description: 'Time unit for wait type steps (seconds, minutes, hours)',
                        type: 'string',
                        enum: ['s', 'm', 'h']
                    ),
                    new OA\Property(
                        property: 'endpointIds',
                        description: 'Array of endpoint IDs for endpoint type steps',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                ]
            )
        ),
        new OA\Property(property: 'userId', description: 'User ID who owns this endpoint', type: 'string'),
        new OA\Property(property: 'isPublic', description: 'Whether this endpoint is publicly available', type: 'boolean'),
        new OA\Property(property: 'createdAt', description: 'Creation timestamp', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', description: 'Last update timestamp', type: 'string', format: 'date-time'),
    ],
    example: [
        'id' => '680d668e20f7ecb5bc026722',
        'name' => 'My SMS Endpoint',
        'type' => 'sms',
        'value' => '09000000000',
        'userId' => 'userId',
        'isPublic' => false,
        'createdAt' => '2024-01-01T10:00:00Z',
        'updatedAt' => '2024-01-01T10:00:00Z',
    ]
)]
class EndpointSchema {}

#[OA\Schema(
    schema: 'EndpointInput',
    description: 'Input schema for creating/updating endpoints. Structure varies based on type.',
    required: ['name', 'type'],
    properties: [
        new OA\Property(property: 'name', description: 'Display name for the endpoint', type: 'string'),
        new OA\Property(
            property: 'type',
            description: 'Type of the endpoint',
            type: 'string',
            enum: ['sms', 'call', 'email', 'telegram', 'teams', 'matter-most', 'flow']
        ),
        new OA\Property(property: 'value', description: 'Primary value (phone number, email address, etc.) - required for most types', type: 'string'),
        new OA\Property(property: 'chatId', description: 'Chat ID for telegram type endpoints', type: 'string'),
        new OA\Property(property: 'threadId', description: 'Thread ID for telegram type endpoints', type: 'string'),
        new OA\Property(property: 'botToken', description: 'Bot token for telegram type endpoints', type: 'string'),
        new OA\Property(property: 'isPublic', description: 'Whether this endpoint is publicly available', type: 'boolean'),
        new OA\Property(
            property: 'steps',
            description: 'Steps array for flow type endpoints',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(
                        property: 'type',
                        description: 'Step type - wait for delay or endpoint for notification',
                        type: 'string',
                        enum: ['wait', 'endpoint']
                    ),
                    new OA\Property(
                        property: 'duration',
                        description: 'Duration for wait type steps',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'timeUnit',
                        description: 'Time unit for wait type steps (seconds, minutes, hours)',
                        type: 'string',
                        enum: ['s', 'm', 'h']
                    ),
                    new OA\Property(
                        property: 'endpointIds',
                        description: 'Array of endpoint IDs for endpoint type steps',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                ]
            )
        ),
    ]
)]
class EndpointInputSchema {}
