<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Webhooks',
    description: 'Inbound integrations (no JWT). API alerts use `apiToken` header; external systems use webhook tokens in the URL path.'
)]
class WebhookDocs
{
    #[OA\Post(
        path: '/api/v1/fire-alert',
        operationId: 'webhookFireApiAlert',
        summary: 'Fire an API alert',
        description: 'Authenticated with API alert token (see `ApiAlertAuth` middleware), not bearer JWT.',
        tags: ['Webhooks'],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(type: 'object', description: 'Alert payload fields depend on integration')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
            new OA\Response(response: 401, description: 'Invalid token'),
            new OA\Response(response: 429, description: 'Rate limited'),
        ]
    )]
    public function fireAlert() {}

    #[OA\Post(
        path: '/api/v1/resolve-alert',
        operationId: 'webhookResolveApiAlert',
        summary: 'Resolve an API alert',
        tags: ['Webhooks'],
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
            new OA\Response(response: 401, description: 'Invalid token'),
        ]
    )]
    public function resolveAlert() {}

    #[OA\Post(
        path: '/api/v1/stop-alert',
        operationId: 'webhookStopApiAlert',
        summary: 'Stop an API alert (alias of resolve)',
        tags: ['Webhooks'],
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
        ]
    )]
    public function stopAlert() {}

    #[OA\Post(
        path: '/api/v1/status-alert',
        operationId: 'webhookStatusApiAlert',
        summary: 'Post API alert status update',
        tags: ['Webhooks'],
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
        ]
    )]
    public function statusAlert() {}

    #[OA\Post(
        path: '/api/v1/notification-alert',
        operationId: 'webhookNotificationAlert',
        summary: 'Post notification-type alert payload',
        tags: ['Webhooks'],
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
        ]
    )]
    public function notificationAlert() {}

    #[OA\Post(
        path: '/api/v1/sentry-alert/{token}',
        operationId: 'webhookSentryAlert',
        summary: 'Sentry webhook',
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'token', description: 'Data source webhook token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
            new OA\Response(response: 401, description: 'Invalid token'),
        ]
    )]
    public function sentryAlert() {}

    #[OA\Post(
        path: '/api/v1/splunk-alert/{token}',
        operationId: 'webhookSplunkAlert',
        summary: 'Splunk webhook',
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
        ]
    )]
    public function splunkAlert() {}

    #[OA\Post(
        path: '/api/v1/zabbix-alert/{token}',
        operationId: 'webhookZabbixAlert',
        summary: 'Zabbix webhook',
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
        ]
    )]
    public function zabbixAlert() {}

    #[OA\Post(
        path: '/api/v1/grafana-alert/{token}',
        operationId: 'webhookGrafanaAlert',
        summary: 'Grafana webhook',
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
        ]
    )]
    public function grafanaAlert() {}

    #[OA\Post(
        path: '/api/v1/pmm-alert/{token}',
        operationId: 'webhookPmmAlert',
        summary: 'PMM webhook',
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
        ]
    )]
    public function pmmAlert() {}
}
