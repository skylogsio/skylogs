<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Data Sources',
    description: 'Manage monitoring data sources (Prometheus, Grafana, Sentry, etc.)'
)]
class DataSourceDocs
{
    #[OA\Get(
        path: '/api/v1/data-source',
        operationId: 'getDataSources',
        summary: 'Get list of data sources',
        security: [['bearerAuth' => []]],
        tags: ['Data Sources'],
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
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of data sources',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/DataSource')
                        )
                    ]
                )
            )
        ]
    )]
    public function index() {}

    #[OA\Get(
        path: '/api/v1/data-source/types',
        operationId: 'getDataSourceTypes',
        summary: 'Get available data source types',
        security: [['bearerAuth' => []]],
        tags: ['Data Sources'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of data source types',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        enum: ['prometheus', 'sentry', 'grafana', 'pmm', 'zabbix', 'splunk', 'elastic']
                    )
                )
            )
        ]
    )]
    public function getTypes() {}

    #[OA\Get(
        path: '/api/v1/data-source/{id}',
        operationId: 'getDataSource',
        summary: 'Get data source by ID',
        security: [['bearerAuth' => []]],
        tags: ['Data Sources'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Data source details',
                content: new OA\JsonContent(ref: '#/components/schemas/DataSource')
            ),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function show() {}

    #[OA\Get(
        path: '/api/v1/data-source/status/{id}',
        operationId: 'checkDataSourceConnection',
        summary: 'Check if data source is connected',
        security: [['bearerAuth' => []]],
        tags: ['Data Sources'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Connection status',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'isConnected', type: 'boolean')
                    ]
                )
            )
        ]
    )]
    public function isConnected() {}

    #[OA\Post(
        path: '/api/v1/data-source',
        operationId: 'createDataSource',
        summary: 'Create new data source',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'type', 'url'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Production Prometheus'),
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        enum: ['prometheus', 'sentry', 'grafana', 'pmm', 'zabbix', 'splunk', 'elastic'],
                        example: 'prometheus'
                    ),
                    new OA\Property(property: 'url', type: 'string', example: 'https://prometheus.example.com'),
                    new OA\Property(property: 'api_token', type: 'string'),
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'password', type: 'string')
                ]
            )
        ),
        tags: ['Data Sources'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Data source created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/DataSource')
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function create() {}

    #[OA\Put(
        path: '/api/v1/data-source/{id}',
        operationId: 'updateDataSource',
        summary: 'Update data source',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/DataSourceInput')
        ),
        tags: ['Data Sources'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Data source updated'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function update() {}

    #[OA\Delete(
        path: '/api/v1/data-source/{id}',
        operationId: 'deleteDataSource',
        summary: 'Delete data source',
        security: [['bearerAuth' => []]],
        tags: ['Data Sources'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Data source deleted'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function delete() {}
}

#[OA\Schema(
    schema: 'DataSource',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'type', type: 'string'),
        new OA\Property(property: 'url', type: 'string'),
        new OA\Property(property: 'webhookToken', type: 'string'),
        new OA\Property(property: 'copy', description: 'Webhook URL', type: 'string'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time')
    ]
)]
class DataSourceSchema {}

#[OA\Schema(
    schema: 'DataSourceInput',
    required: ['name', 'type', 'url'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'type', type: 'string'),
        new OA\Property(property: 'url', type: 'string'),
        new OA\Property(property: 'api_token', type: 'string'),
        new OA\Property(property: 'username', type: 'string'),
        new OA\Property(property: 'password', type: 'string')
    ]
)]
class DataSourceInputSchema {}
