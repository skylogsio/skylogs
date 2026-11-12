<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Prometheus',
    description: 'Prometheus helper endpoints for alert-rule UI (rules, labels, fired alerts). Prefer `/alert-rule/create-data/*` when building create forms.'
)]
class PrometheusDocs
{
    #[OA\Get(
        path: '/api/v1/prometheus/rules',
        operationId: 'getPrometheusRules',
        summary: 'Get Prometheus alert rule names',
        security: [['bearerAuth' => []]],
        tags: ['Prometheus'],
        parameters: [
            new OA\Parameter(
                name: 'data_source_id',
                description: 'Prometheus data source id',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'External rule names (cached)',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'string'))
            ),
        ]
    )]
    public function rules() {}

    #[OA\Get(
        path: '/api/v1/prometheus/labels',
        operationId: 'getPrometheusLabels',
        summary: 'Get Prometheus label names',
        security: [['bearerAuth' => []]],
        tags: ['Prometheus'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Label names',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'string'))
            ),
        ]
    )]
    public function labels() {}

    #[OA\Get(
        path: '/api/v1/prometheus/label-values/{label}',
        operationId: 'getPrometheusLabelValues',
        summary: 'Get values for a Prometheus label',
        security: [['bearerAuth' => []]],
        tags: ['Prometheus'],
        parameters: [
            new OA\Parameter(name: 'label', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Label values',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'string'))
            ),
        ]
    )]
    public function labelValues() {}

    #[OA\Get(
        path: '/api/v1/prometheus/triggered',
        operationId: 'getPrometheusTriggeredAlerts',
        summary: 'Get currently firing Prometheus alerts',
        security: [['bearerAuth' => []]],
        tags: ['Prometheus'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Triggered alerts from Prometheus (cached ~5s)',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))
            ),
        ]
    )]
    public function triggered() {}
}
