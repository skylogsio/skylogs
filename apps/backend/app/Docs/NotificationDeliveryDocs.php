<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Notification Deliveries',
    description: 'One record per message sent to one endpoint, with every delivery attempt and its provider response.'
)]
class NotificationDeliveryDocs
{
    #[OA\Get(
        path: '/api/v1/notification-deliveries',
        operationId: 'getNotificationDeliveries',
        summary: 'List notification deliveries (paginated, newest first)',
        description: 'Admins see every delivery; other users see deliveries to endpoints they own.',
        security: [['bearerAuth' => []]],
        tags: ['Notification Deliveries'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25, maximum: 100)),
            new OA\Parameter(name: 'notifyId', in: 'query', schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'endpointId', in: 'query', schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'sending', 'sent', 'failed', 'skipped'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated deliveries',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'current_page', type: 'integer'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/NotificationDelivery')),
                        new OA\Property(property: 'last_page', type: 'integer'),
                        new OA\Property(property: 'per_page', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function index() {}

    #[OA\Get(
        path: '/api/v1/notification-deliveries/{id}',
        operationId: 'getNotificationDelivery',
        summary: 'Get a notification delivery with its attempt log',
        security: [['bearerAuth' => []]],
        tags: ['Notification Deliveries'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Delivery', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/NotificationDelivery')])),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show() {}

    #[OA\Post(
        path: '/api/v1/notification-deliveries/{id}/retry',
        operationId: 'retryNotificationDelivery',
        summary: 'Retry a failed delivery',
        description: 'Grants one more attempt and queues it. The same stored message (including any custom template) is sent to the endpoint as it is configured now.',
        security: [['bearerAuth' => []]],
        tags: ['Notification Deliveries'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Retry queued', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/NotificationDelivery')])),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'The delivery is not in the failed state'),
        ]
    )]
    public function retry() {}
}

#[OA\Schema(
    schema: 'NotificationDelivery',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'notifyId', type: 'string', nullable: true),
        new OA\Property(property: 'source', type: 'string', enum: ['alert', 'incident_policy', 'flow']),
        new OA\Property(property: 'sourceId', type: 'string', nullable: true, description: 'Alert rule id, or incident id for incident pages'),
        new OA\Property(property: 'flowEndpointId', type: 'string', nullable: true),
        new OA\Property(property: 'flowStepIndex', type: 'integer', nullable: true),
        new OA\Property(property: 'endpointId', type: 'string'),
        new OA\Property(property: 'endpointType', type: 'string', enum: ['telegram', 'bale', 'sms', 'call', 'teams', 'discord', 'email', 'matter-most']),
        new OA\Property(property: 'endpointName', type: 'string'),
        new OA\Property(property: 'userId', type: 'string', description: 'Owner of the endpoint'),
        new OA\Property(
            property: 'message',
            type: 'object',
            description: 'The exact message this endpoint receives; overrides are keyed by endpoint type',
            properties: [
                new OA\Property(property: 'body', type: 'string'),
                new OA\Property(property: 'overrides', type: 'object'),
            ]
        ),
        new OA\Property(property: 'templateApplied', type: 'boolean'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'sending', 'sent', 'failed', 'skipped']),
        new OA\Property(property: 'attempts', type: 'integer'),
        new OA\Property(property: 'maxAttempts', type: 'integer'),
        new OA\Property(property: 'canRetry', type: 'boolean'),
        new OA\Property(property: 'nextRetryAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'lastError', type: 'string', nullable: true),
        new OA\Property(
            property: 'attemptLog',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'attempt', type: 'integer'),
                    new OA\Property(property: 'at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'status', type: 'string', enum: ['sent', 'failed']),
                    new OA\Property(property: 'retryable', type: 'boolean'),
                    new OA\Property(property: 'httpStatus', type: 'integer', nullable: true),
                    new OA\Property(property: 'providerMessageId', type: 'string', nullable: true),
                    new OA\Property(property: 'response', description: 'Provider response, truncated; null for providers that return nothing (Teams, Discord, email)'),
                    new OA\Property(property: 'error', type: 'string', nullable: true),
                    new OA\Property(property: 'durationMs', type: 'integer'),
                    new OA\Property(property: 'trigger', type: 'string', enum: ['auto', 'manual']),
                ]
            )
        ),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
class NotificationDeliverySchema {}
