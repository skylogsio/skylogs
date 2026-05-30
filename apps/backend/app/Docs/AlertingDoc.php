<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'AlertRule',
    description: 'Manage alert rules, notifications, access, tags, and behavior rules'
)]
class AlertingDoc
{
    // ----------------------------
    // GET /api/v1/alert-rule
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/alert-rule',
        operationId: 'getAlertRules',
        summary: 'List alert rules',
        description: 'Returns a paginated list of alert rules visible to the authenticated user. Pinned rules appear first.',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'page', description: 'Page number', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'alertname', description: 'Filter by alert rule name (case-insensitive partial match)', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'userId', description: 'Filter by owner or shared user id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'types', description: 'Comma-separated alert types', in: 'query', schema: new OA\Schema(type: 'string'), example: 'api,prometheus,elastic'),
            new OA\Parameter(name: 'tags', description: 'Comma-separated tags (all must match)', in: 'query', schema: new OA\Schema(type: 'string'), example: 'production,critical'),
            new OA\Parameter(name: 'silentStatus', description: 'Filter by silence state for the current user', in: 'query', schema: new OA\Schema(type: 'string', enum: ['silent', 'active'])),
            new OA\Parameter(name: 'endpointId', description: 'Filter by linked endpoint id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', description: 'Filter by alert state', in: 'query', schema: new OA\Schema(type: 'string', example: 'critical')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated alert rules',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'current_page', type: 'integer'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleListItem')),
                        new OA\Property(property: 'last_page', type: 'integer'),
                        new OA\Property(property: 'per_page', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index() {}

    // ----------------------------
    // GET /api/v1/alert-rule/{id}
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/alert-rule/{id}',
        operationId: 'getAlertRule',
        summary: 'Get alert rule by id',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Alert rule details',
                content: new OA\JsonContent(ref: '#/components/schemas/AlertRuleDetail')
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show() {}

    // ----------------------------
    // POST /api/v1/alert-rule
    // ----------------------------
    #[OA\Post(
        path: '/api/v1/alert-rule',
        operationId: 'createAlertRule',
        summary: 'Create alert rule',
        description: 'Creates an alert rule. Required and optional fields depend on the `type` value.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AlertRuleStoreInput')
        ),
        tags: ['AlertRule'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Created successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
        ]
    )]
    public function store() {}

    // ----------------------------
    // PUT /api/v1/alert-rule/{id}
    // ----------------------------
    #[OA\Put(
        path: '/api/v1/alert-rule/{id}',
        operationId: 'updateAlertRule',
        summary: 'Update alert rule',
        description: 'Updates an alert rule. Updatable fields depend on the alert type. Non-admin users can only update their own rules.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AlertRuleUpdateInput')
        ),
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Updated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function update() {}

    // ----------------------------
    // DELETE /api/v1/alert-rule/{id}
    // ----------------------------
    #[OA\Delete(
        path: '/api/v1/alert-rule/{id}',
        operationId: 'deleteAlertRule',
        summary: 'Delete alert rule',
        description: 'Deletes the alert rule for admins/owners, or removes the current user access and endpoints for shared users.',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Deleted',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function destroy() {}

    // ----------------------------
    // POST /api/v1/alert-rule/pin/{id}
    // ----------------------------
    #[OA\Post(
        path: '/api/v1/alert-rule/pin/{id}',
        operationId: 'pinAlertRule',
        summary: 'Toggle pin on alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pin toggled',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'isPin', type: 'boolean'),
                    ]
                )
            ),
        ]
    )]
    public function pin() {}

    // ----------------------------
    // POST /api/v1/alert-rule/acknowledge/{id}
    // ----------------------------
    #[OA\Post(
        path: '/api/v1/alert-rule/acknowledge/{id}',
        operationId: 'acknowledgeAlertRule',
        summary: 'Acknowledge an alert (current user)',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Acknowledged',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function acknowledge() {}

    // ----------------------------
    // GET /api/v1/alert-rule/acknowledgeL/{id}
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/alert-rule/acknowledgeL/{id}',
        operationId: 'acknowledgeLoginLink',
        summary: 'Acknowledge alert using login link (system user)',
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Acknowledged or already acknowledged',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string', nullable: true),
                    ]
                )
            ),
        ]
    )]
    public function acknowledgeLoginLink() {}

    // ----------------------------
    // POST /api/v1/alert-rule/resolve/{id}
    // ----------------------------
    #[OA\Post(
        path: '/api/v1/alert-rule/resolve/{id}',
        operationId: 'resolveAlertRule',
        summary: 'Manually resolve alert',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Resolved',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function resolve() {}

    // ----------------------------
    // POST /api/v1/alert-rule/silent/{id}
    // ----------------------------
    #[OA\Post(
        path: '/api/v1/alert-rule/silent/{id}',
        operationId: 'toggleSilentAlertRule',
        summary: 'Toggle silence for current user on a single alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Silence toggled',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'isSilent', type: 'boolean'),
                    ]
                )
            ),
        ]
    )]
    public function silentToggle() {}

    // ----------------------------
    // GET /api/v1/alert-rule/filter-endpoints
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/alert-rule/filter-endpoints',
        operationId: 'filterEndpoints',
        summary: 'Get selectable endpoints for alert rules',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        responses: [
            new OA\Response(response: 200, description: 'Selectable endpoints'),
        ]
    )]
    public function filterEndpoints() {}

    // ----------------------------
    // GET /api/v1/alert-rule/types
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/alert-rule/types',
        operationId: 'getAlertRuleTypes',
        summary: 'List available alert rule types',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Alert rule type enum values',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(type: 'string', enum: [
                        'api', 'notification', 'prometheus', 'sentry', 'grafana', 'pmm',
                        'zabbix', 'splunk', 'elastic', 'health', 'metabase', 'victoria_logs',
                    ])
                )
            ),
        ]
    )]
    public function getTypes() {}

    // ----------------------------
    // GET /api/v1/alert-rule/history/{id}
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/alert-rule/history/{id}',
        operationId: 'getAlertHistory',
        summary: 'Get history for an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 50)),
            new OA\Parameter(name: 'from', description: 'Start datetime (Y-m-d H:i)', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time', example: '2026-01-01 00:00')),
            new OA\Parameter(name: 'to', description: 'End datetime (Y-m-d H:i)', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time', example: '2026-01-31 23:59')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated alert history'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function history() {}

    // ----------------------------
    // GET /api/v1/alert-rule/triggered/{id}
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/alert-rule/triggered/{id}',
        operationId: 'getTriggeredAlerts',
        summary: 'Get triggered/fired alerts for an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fired alert instances or check data'),
        ]
    )]
    public function firedAlerts() {}

    // ----------------------------
    // Create-data endpoints
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/alert-rule/create-data',
        operationId: 'getAlertRuleCreateData',
        summary: 'Get form data for creating an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Endpoints and selectable users',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'endpoints', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'users', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
        ]
    )]
    public function createData() {}

    #[OA\Get(
        path: '/api/v1/alert-rule/create-data/data-source/{type}',
        operationId: 'getAlertRuleDataSources',
        summary: 'Get data sources by type for alert rule creation',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(
                name: 'type',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: [
                    'prometheus', 'sentry', 'grafana', 'pmm', 'zabbix', 'splunk', 'elastic', 'victoria_logs',
                ])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Data sources',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string'),
                            new OA\Property(property: 'name', type: 'string'),
                        ]
                    )
                )
            ),
        ]
    )]
    public function createDataSources() {}

    #[OA\Get(
        path: '/api/v1/alert-rule/create-data/zabbix',
        operationId: 'getAlertRuleZabbixData',
        summary: 'Get Zabbix hosts, actions, and severities',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Zabbix metadata',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'hosts', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'actions', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'severities', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
        ]
    )]
    public function createZabbixData() {}

    #[OA\Get(
        path: '/api/v1/alert-rule/create-data/rules',
        operationId: 'getAlertRuleExternalRules',
        summary: 'Get external alert rule names (Prometheus/Grafana)',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['prometheus', 'grafana'])),
            new OA\Parameter(name: 'dataSourceId', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'External rule names'),
        ]
    )]
    public function createRules() {}

    #[OA\Get(
        path: '/api/v1/alert-rule/create-data/labels',
        operationId: 'getAlertRulePrometheusLabels',
        summary: 'Get Prometheus labels',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        responses: [
            new OA\Response(response: 200, description: 'Prometheus labels'),
        ]
    )]
    public function createLabels() {}

    #[OA\Get(
        path: '/api/v1/alert-rule/create-data/label-values/{label}',
        operationId: 'getAlertRulePrometheusLabelValues',
        summary: 'Get Prometheus label values',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'label', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Label values'),
        ]
    )]
    public function createLabelValues() {}

    // ----------------------------
    // Group-action endpoints
    // ----------------------------
    #[OA\Post(
        path: '/api/v1/alert-rule/group-action/silent',
        operationId: 'groupSilentAlertRules',
        summary: 'Silence filtered alert rules for current user',
        description: 'Uses the same query filters as the alert rule list endpoint.',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'alertname', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'userId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'types', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tags', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'silentStatus', in: 'query', schema: new OA\Schema(type: 'string', enum: ['silent', 'active'])),
            new OA\Parameter(name: 'endpointId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Rules silenced',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
        ]
    )]
    public function groupSilent() {}

    #[OA\Post(
        path: '/api/v1/alert-rule/group-action/unsilent',
        operationId: 'groupUnsilentAlertRules',
        summary: 'Remove silence from filtered alert rules for current user',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'alertname', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'userId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'types', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tags', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'silentStatus', in: 'query', schema: new OA\Schema(type: 'string', enum: ['silent', 'active'])),
            new OA\Parameter(name: 'endpointId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Silence removed',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
        ]
    )]
    public function groupUnsilent() {}

    #[OA\Post(
        path: '/api/v1/alert-rule/group-action/delete',
        operationId: 'groupDeleteAlertRules',
        summary: 'Delete filtered alert rules',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'alertname', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'userId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'types', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tags', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'endpointId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Rules deleted or access removed',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
        ]
    )]
    public function groupDelete() {}

    #[OA\Post(
        path: '/api/v1/alert-rule/group-action/add-user-notify',
        operationId: 'groupAddUserNotifyAlertRules',
        summary: 'Add users, teams, or endpoints to filtered alert rules',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'alertname', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'userId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'types', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tags', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'endpointId', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'userIds', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'teamIds', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'endpointIds', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Access or notifications updated',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
        ]
    )]
    public function groupAddUserNotify() {}

    // ----------------------------
    // Tag endpoints
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/alert-rule-tag',
        operationId: 'getAllAlertRuleTags',
        summary: 'List all alert rule tags',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        responses: [
            new OA\Response(response: 200, description: 'All tags'),
        ]
    )]
    public function tagsAll() {}

    #[OA\Get(
        path: '/api/v1/alert-rule-tag/{id}',
        operationId: 'getAlertRuleTags',
        summary: 'Get tags for an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Alert rule tags',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'string'))
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function tagsShow() {}

    #[OA\Put(
        path: '/api/v1/alert-rule-tag/{id}',
        operationId: 'updateAlertRuleTags',
        summary: 'Update tags for an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tags updated',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function tagsUpdate() {}

    // ----------------------------
    // Behavior rule endpoints
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/alert-rule-behavior-rule/{alertRuleId}',
        operationId: 'getAlertRuleBehaviorRules',
        summary: 'List behavior rules for an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'alertRuleId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Behavior rules',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'rules', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleBehaviorRule')),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function behaviorRulesIndex() {}

    #[OA\Post(
        path: '/api/v1/alert-rule-behavior-rule/{alertRuleId}',
        operationId: 'createAlertRuleBehaviorRule',
        summary: 'Create a notification behavior rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'alertRuleId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'filters', 'endpointIds'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['notification']),
                    new OA\Property(
                        property: 'filters',
                        type: 'array',
                        items: new OA\Items(
                            required: ['key', 'value'],
                            properties: [
                                new OA\Property(property: 'key', type: 'string'),
                                new OA\Property(property: 'value', type: 'string'),
                            ]
                        )
                    ),
                    new OA\Property(property: 'endpointIds', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Behavior rule created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'rule', ref: '#/components/schemas/AlertRuleBehaviorRule'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function behaviorRulesStore() {}

    #[OA\Put(
        path: '/api/v1/alert-rule-behavior-rule/{alertRuleId}/{ruleId}',
        operationId: 'updateAlertRuleBehaviorRule',
        summary: 'Update a notification behavior rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'alertRuleId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'ruleId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'filters',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'key', type: 'string'),
                                new OA\Property(property: 'value', type: 'string'),
                            ]
                        )
                    ),
                    new OA\Property(property: 'endpointIds', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Behavior rule updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'rule', ref: '#/components/schemas/AlertRuleBehaviorRule'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function behaviorRulesUpdate() {}

    #[OA\Delete(
        path: '/api/v1/alert-rule-behavior-rule/{alertRuleId}/{ruleId}',
        operationId: 'deleteAlertRuleBehaviorRule',
        summary: 'Delete a behavior rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'alertRuleId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'ruleId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Behavior rule deleted',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function behaviorRulesDelete() {}

    // ----------------------------
    // Notify endpoints
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/alert-rule-notify/{id}',
        operationId: 'getAlertRuleNotifyData',
        summary: 'Get notification endpoints for an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current and selectable endpoints',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'alertEndpoints', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'selectableEndpoints', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
        ]
    )]
    public function notifyCreate() {}

    #[OA\Put(
        path: '/api/v1/alert-rule-notify/{id}',
        operationId: 'updateAlertRuleNotify',
        summary: 'Add notification endpoints to an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'endpointIds', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Endpoints added',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
        ]
    )]
    public function notifyStore() {}

    #[OA\Delete(
        path: '/api/v1/alert-rule-notify/{alertId}/{endpointId}',
        operationId: 'deleteAlertRuleNotifyEndpoint',
        summary: 'Remove a notification endpoint from an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'alertId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'endpointId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Endpoint removed',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
        ]
    )]
    public function notifyDelete() {}

    #[OA\Post(
        path: '/api/v1/alert-rule-notify/test/{id}',
        operationId: 'testAlertRuleNotify',
        summary: 'Send a test notification for an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Test notification queued',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function notifyTest() {}

    #[OA\Get(
        path: '/api/v1/alert-rule-notify/batchAlert',
        operationId: 'getBatchAlertRuleNotifyData',
        summary: 'Get selectable endpoints for batch notification assignment',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Selectable endpoints',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'selectableEndpoints', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
        ]
    )]
    public function notifyBatchCreate() {}

    #[OA\Put(
        path: '/api/v1/alert-rule-notify/batchAlert',
        operationId: 'updateBatchAlertRuleNotify',
        summary: 'Add endpoints to multiple alert rules',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'alertIds', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'endpoints', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Batch update completed',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
        ]
    )]
    public function notifyBatchStore() {}

    // ----------------------------
    // User access endpoints
    // ----------------------------
    #[OA\Get(
        path: '/api/v1/alert-rule-user/{id}',
        operationId: 'getAlertRuleUserAccessData',
        summary: 'Get user and team access data for an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Users and teams',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'alertUsers', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'selectableUsers', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'alertTeams', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'selectableTeams', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function userAccessCreate() {}

    #[OA\Put(
        path: '/api/v1/alert-rule-user/{id}',
        operationId: 'updateAlertRuleUserAccess',
        summary: 'Add users or teams to an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'userIds', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'teamIds', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Access updated',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function userAccessStore() {}

    #[OA\Delete(
        path: '/api/v1/alert-rule-user/{alertId}/{userId}',
        operationId: 'deleteAlertRuleUserAccess',
        summary: 'Remove a user or team from an alert rule',
        security: [['bearerAuth' => []]],
        tags: ['AlertRule'],
        parameters: [
            new OA\Parameter(name: 'alertId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Access removed',
                content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function userAccessDelete() {}
}

#[OA\Schema(
    schema: 'StatusResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', nullable: true),
    ]
)]
class AlertRuleStatusResponseSchema {}

#[OA\Schema(
    schema: 'AlertRuleExtraField',
    properties: [
        new OA\Property(property: 'key', type: 'string'),
        new OA\Property(property: 'value', type: 'string'),
    ]
)]
class AlertRuleExtraFieldSchema {}

#[OA\Schema(
    schema: 'AlertRuleListItem',
    properties: [
        new OA\Property(property: '_id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'type', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'teamIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'showAcknowledgeBtn', type: 'boolean'),
        new OA\Property(property: 'hasActionAccess', type: 'boolean'),
        new OA\Property(property: 'statusLabel', type: 'string', example: 'critical'),
        new OA\Property(property: 'status_label', type: 'string'),
        new OA\Property(property: 'statusCount', type: 'integer'),
        new OA\Property(property: 'isSilent', type: 'boolean'),
        new OA\Property(property: 'is_silent', type: 'boolean'),
        new OA\Property(property: 'isPinned', type: 'boolean'),
        new OA\Property(property: 'countEndpoints', type: 'integer'),
        new OA\Property(property: 'count_endpoints', type: 'integer'),
        new OA\Property(property: 'extraField', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleExtraField')),
    ]
)]
class AlertRuleListItemSchema {}

#[OA\Schema(
    schema: 'AlertRuleDetail',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleListItem'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'ownerName', type: 'string'),
                new OA\Property(property: 'dataSourceLabels', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                new OA\Property(property: 'dataSourceId', type: 'string', nullable: true),
                new OA\Property(property: 'dataSourceAlertName', type: 'string', nullable: true),
                new OA\Property(property: 'queryType', type: 'string', enum: ['dynamic', 'textQuery'], nullable: true),
                new OA\Property(property: 'queryText', type: 'string', nullable: true),
                new OA\Property(property: 'queryObject', type: 'object', nullable: true),
                new OA\Property(property: 'enableAutoResolve', type: 'boolean', nullable: true),
                new OA\Property(property: 'autoResolveMinutes', type: 'integer', nullable: true),
                new OA\Property(property: 'rules', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleBehaviorRule')),
            ]
        ),
    ]
)]
class AlertRuleDetailSchema {}

#[OA\Schema(
    schema: 'AlertRuleStoreInput',
    required: ['name', 'type'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'High CPU usage'),
        new OA\Property(
            property: 'type',
            type: 'string',
            enum: ['api', 'notification', 'prometheus', 'sentry', 'grafana', 'pmm', 'zabbix', 'splunk', 'elastic', 'victoria_logs', 'metabase']
        ),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'showAcknowledgeBtn', type: 'boolean', default: false),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'userIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'teamIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'endpointIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'dataSourceIds', description: 'Required for sentry, splunk, metabase, zabbix, and dynamic grafana/prometheus/pmm rules', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'dataSourceAlertName', description: 'Required for sentry, splunk, metabase, and dynamic grafana/prometheus/pmm rules', type: 'string'),
        new OA\Property(property: 'dataSourceId', description: 'Required for elastic and victoria_logs', type: 'string'),
        new OA\Property(property: 'queryType', description: 'For grafana, pmm, prometheus', type: 'string', enum: ['dynamic', 'textQuery']),
        new OA\Property(property: 'queryText', type: 'string'),
        new OA\Property(property: 'queryObject', type: 'object'),
        new OA\Property(property: 'extraField', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleExtraField')),
        new OA\Property(property: 'hosts', description: 'Zabbix only', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'actions', description: 'Zabbix only', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'severities', description: 'Zabbix only', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'enableAutoResolve', description: 'API type only', type: 'boolean'),
        new OA\Property(property: 'autoResolveMinutes', description: 'API type only', type: 'integer'),
        new OA\Property(property: 'dataviewName', description: 'Elastic only', type: 'string'),
        new OA\Property(property: 'dataviewTitle', description: 'Elastic only', type: 'string'),
        new OA\Property(property: 'queryString', description: 'Elastic and victoria_logs', type: 'string'),
        new OA\Property(property: 'conditionType', description: 'Elastic and victoria_logs', type: 'string', enum: ['greaterOrEqual', 'lessOrEqual']),
        new OA\Property(property: 'countDocument', description: 'Elastic and victoria_logs', type: 'integer'),
        new OA\Property(property: 'minutes', description: 'Elastic and victoria_logs', type: 'integer'),
    ]
)]
class AlertRuleStoreInputSchema {}

#[OA\Schema(
    schema: 'AlertRuleUpdateInput',
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'showAcknowledgeBtn', type: 'boolean'),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'userIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'teamIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'endpointIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'dataSourceAlertName', type: 'string'),
        new OA\Property(property: 'dataSourceId', type: 'string'),
        new OA\Property(property: 'queryType', type: 'string', enum: ['dynamic', 'textQuery']),
        new OA\Property(property: 'queryText', type: 'string'),
        new OA\Property(property: 'queryObject', type: 'object'),
        new OA\Property(property: 'extraField', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleExtraField')),
        new OA\Property(property: 'hosts', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'actions', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'severities', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'enableAutoResolve', type: 'boolean'),
        new OA\Property(property: 'autoResolveMinutes', type: 'integer'),
        new OA\Property(property: 'dataviewName', type: 'string'),
        new OA\Property(property: 'dataviewTitle', type: 'string'),
        new OA\Property(property: 'queryString', type: 'string'),
        new OA\Property(property: 'conditionType', type: 'string', enum: ['greaterOrEqual', 'lessOrEqual']),
        new OA\Property(property: 'countDocument', type: 'integer'),
        new OA\Property(property: 'minutes', type: 'integer'),
    ]
)]
class AlertRuleUpdateInputSchema {}

#[OA\Schema(
    schema: 'AlertRuleBehaviorRule',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'type', type: 'string', enum: ['notification']),
        new OA\Property(property: 'filters', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleExtraField')),
        new OA\Property(property: 'endpointIds', type: 'array', items: new OA\Items(type: 'string')),
    ]
)]
class AlertRuleBehaviorRuleSchema {}
