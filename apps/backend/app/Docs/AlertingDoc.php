<?php


namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'AlertRule',
    description: 'Manage alert rules'
)]
class AlertingDoc
{
    // ----------------------------
    // GET /api/v1/alert-rule
    // ----------------------------
    #[OA\Get(
        path: "/api/v1/alert-rule",
        operationId: "getAlertRules",
        summary: "List alert rules",
        security: [["bearerAuth" => []]],
        tags: ["AlertRule"],
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
                name: 'alertname',
                in: 'query',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'userId',
                in: 'query',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'types',
                description: 'separate types that you want to filter via , ',
                in: 'query',
                schema: new OA\Schema(type: 'string'),
                example: 'api,prometheus'
            ),
            new OA\Parameter(
                name: 'tags',
                description: 'separate types that you want to filter via , ',
                in: 'query',
                schema: new OA\Schema(type: 'string'),
                example: 'api,prometheus'
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "OK"),
        ]
    )]
    public function index()
    {
    }


    // ----------------------------
    // GET /api/v1/alert-rule/{id}
    // ----------------------------
    #[OA\Get(
        path: "/api/v1/alert-rule/{id}",
        operationId: "getAlertRule",
        summary: "Get alert rule by id",
        security: [["bearerAuth" => []]],
        tags: ["AlertRule"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", example: "64b59f1a...")),
        ],
        responses: [
            new OA\Response(response: 200, description: "OK"),
            new OA\Response(response: 404, description: "Not Found"),
        ]
    )]
    public function show()
    {
    }

/*
    // ----------------------------
    // POST /api/v1/alert-rule
    // ----------------------------
    #[OA\Post(
        path: "/api/v1/alert-rule",
        operationId: "createAlertRuleApi",
        summary: "Create Api alert rule",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "High CPU usage"),
                    new OA\Property(property: "type", type: "string", example: "api"),
                    new OA\Property(property: "description", type: "string", example: "Alert when CPU > 80%"),
                    new OA\Property(property: "enableAutoResolve", type: "boolean", example: true),
                    new OA\Property(property: "autoResolveMinutes", type: "integer", example: 30),
                    new OA\Property(property: "userIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "teamIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "endpointIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "tags", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "minutes", type: "integer", example: 5),
                ],
                type: "object"
            )
        ),
        tags: ["AlertRule"],
        responses: [
            new OA\Response(response: 201, description: "Created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function storeApi()
    {
    }

    // ----------------------------
    // POST /api/v1/alert-rule
    // ----------------------------
    #[OA\Post(
        path: "/api/v1/alert-rule",
        operationId: "createAlertRulePrometheus",
        summary: "Create Prometheus alert rule",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "High CPU usage"),
                    new OA\Property(property: "type", type: "string", example: "prometheus"),
                    new OA\Property(property: "description", type: "string", example: "Alert when CPU > 80%"),
                    new OA\Property(property: "dataSourceIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "dataSourceAlertName", type: "string", example: "CPU Alert"),
                    new OA\Property(property: "expression", type: "string", example: "avg(rate(cpu_usage[5m])) > 80"),
                    new OA\Property(property: "queryString", type: "string", example: "error_count > 5"),
                    new OA\Property(property: "userIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "teamIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "endpointIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "tags", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "queryType", type: "string", example: "lucene"),
                    new OA\Property(property: "minutes", type: "integer", example: 5),
                ],
            )
        ),
        tags: ["AlertRule"],
        responses: [
            new OA\Response(response: 201, description: "Created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function storePrometheusGrafana()
    {
    }

    // ----------------------------
    // POST /api/v1/alert-rule
    // ----------------------------
    #[OA\Post(
        path: "/api/v1/alert-rule",
        operationId: "createAlertRuleZabbix",
        summary: "Create zabbix alert rule",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "High CPU usage"),
                    new OA\Property(property: "type", type: "string", example: "zabbix"),
                    new OA\Property(property: "description", type: "string", example: "Alert when CPU > 80%"),
                    new OA\Property(property: "dataSourceIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "hosts", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "actions", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "severities", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "userIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "teamIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "endpointIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "tags", type: "array", items: new OA\Items(type: "string")),
                ],
                type: "object"
            )
        ),
        tags: ["AlertRule"],
        responses: [
            new OA\Response(response: 201, description: "Created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function storeZabbix()
    {
    }*/


    // ----------------------------
    // POST /api/v1/alert-rule
    // ----------------------------
    #[OA\Post(
        path: "/api/v1/alert-rule",
        operationId: "createAlertRuleElastic",
        summary: "Create elastic alert rule",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "High CPU usage"),
                    new OA\Property(property: "type", type: "string", example: "elastic"),
                    new OA\Property(property: "description", type: "string", example: "Alert when CPU > 80%"),
                    new OA\Property(property: "dataSourceId", type: "string", example: "1"),
                    new OA\Property(property: "queryString", type: "string", example: "OriginStatus:>=400 name:myName"),
                    new OA\Property(property: "userIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "teamIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "endpointIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "tags", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "dataviewName", type: "string", example: "My dataview"),
                    new OA\Property(property: "dataviewTitle", type: "string", example: "responses*"),
                    new OA\Property(property: "conditionType", type: "string", enum: ['greaterOrEqual','lessOrEqual'], example: "greaterOrEqual"),
                    new OA\Property(property: "countDocument", type: "integer", example: 5),
                    new OA\Property(property: "minutes", type: "integer", example: 5),
                ],
                type: "object"
            )
        ),
        tags: ["AlertRule"],
        responses: [
            new OA\Response(response: 201, description: "Created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function storeElastic()
    {
    }
/*

    // ----------------------------
    // POST /api/v1/alert-rule
    // ----------------------------
    #[OA\Post(
        path: "/api/v1/alert-rule",
        operationId: "createAlertRule",
        summary: "Create alert rule",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "High CPU usage"),
                    new OA\Property(property: "type", type: "string", example: "prometheus"),
                    new OA\Property(property: "description", type: "string", example: "Alert when CPU > 80%"),
                    new OA\Property(property: "dataSourceIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "dataSourceAlertName", type: "string", example: "CPU Alert"),
                    new OA\Property(property: "dataSourceId", type: "string", example: "1"),
                    new OA\Property(property: "expression", type: "string", example: "avg(rate(cpu_usage[5m])) > 80"),
                    new OA\Property(property: "queryString", type: "string", example: "error_count > 5"),
                    new OA\Property(property: "hosts", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "actions", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "severities", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "enableAutoResolve", type: "boolean", example: true),
                    new OA\Property(property: "autoResolveMinutes", type: "integer", example: 30),
                    new OA\Property(property: "apiToken", type: "string", example: "randomgeneratedtoken"),
                    new OA\Property(property: "userIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "teamIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "endpointIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "silentUserIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "tags", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "url", type: "string", example: "https://example.com/webhook"),
                    new OA\Property(property: "dataviewName", type: "string", example: "My dataview"),
                    new OA\Property(property: "dataviewTitle", type: "string", example: "Dataview title"),
                    new OA\Property(property: "conditionType", type: "string", example: "greater_than"),
                    new OA\Property(property: "countDocument", type: "integer", example: 5),
                    new OA\Property(property: "queryType", type: "string", example: "lucene"),
                    new OA\Property(property: "minutes", type: "integer", example: 5),
                ],
                type: "object"
            )
        ),
        tags: ["AlertRule"],
        responses: [
            new OA\Response(response: 201, description: "Created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store()
    {
    }*/

    // ----------------------------
    // PUT /api/v1/alert-rule/{id}
    // ----------------------------
    #[OA\Put(
        path: "/api/v1/alert-rule/{id}",
        operationId: "updateAlertRule",
        summary: "Update alert rule",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "High CPU usage"),
                    new OA\Property(property: "description", type: "string", example: "Alert when CPU > 80%"),
                    new OA\Property(property: "dataSourceIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "dataSourceAlertName", type: "string", example: "CPU Alert"),
                    new OA\Property(property: "expression", type: "string", example: "avg(rate(cpu_usage[5m])) > 80"),
                    new OA\Property(property: "enableAutoResolve", type: "boolean", example: true),
                    new OA\Property(property: "autoResolveMinutes", type: "integer", example: 30),
                    new OA\Property(property: "userIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "teamIds", type: "array", items: new OA\Items(type: "string")),
                ],
                type: "object"
            )
        ),
        tags: ["AlertRule"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", example: "64b59f1a...")),
        ],
        responses: [
            new OA\Response(response: 200, description: "OK"),
            new OA\Response(response: 404, description: "Not Found"),
        ]
    )]
    public function update()
    {
    }


    // ----------------------------
    // DELETE /api/v1/alert-rule/{id}
    // ----------------------------
    #[OA\Delete(
        path: "/api/v1/alert-rule/{id}",
        operationId: "deleteAlertRule",
        summary: "Delete alert rule",
        security: [["bearerAuth" => []]],
        tags: ["AlertRule"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", example: "64b59f1a...")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Deleted"),
            new OA\Response(response: 404, description: "Not Found"),
        ]
    )]
    public function destroy()
    {
    }


    // ----------------------------
    // POST /api/v1/alert-rule/pin/{id}
    // ----------------------------
    #[OA\Post(
        path: "/api/v1/alert-rule/pin/{id}",
        operationId: "pinAlertRule",
        summary: "Toggle pin on alert rule",
        security: [["bearerAuth" => []]],
        tags: ["AlertRule"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function pin()
    {
    }


    // ----------------------------
    // POST /api/v1/alert-rule/acknowledge/{id}
    // ----------------------------
    #[OA\Post(
        path: "/api/v1/alert-rule/acknowledge/{id}",
        operationId: "acknowledgeAlertRule",
        summary: "Acknowledge an alert (current user)",
        security: [["bearerAuth" => []]],
        tags: ["AlertRule"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function acknowledge()
    {
    }


    // ----------------------------
    // GET /api/v1/alert-rule/acknowledgeL/{id}
    // ----------------------------
    #[OA\Get(
        path: "/api/v1/alert-rule/acknowledgeL/{id}",
        operationId: "acknowledgeLoginLink",
        summary: "Acknowledge alert using login link (system user)",
        tags: ["AlertRule"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function acknowledgeLoginLink()
    {
    }


    // ----------------------------
    // POST /api/v1/alert-rule/resolve/{id}
    // ----------------------------
    #[OA\Post(
        path: "/api/v1/alert-rule/resolve/{id}",
        operationId: "resolveAlertRule",
        summary: "Resolve alert",
        security: [["bearerAuth" => []]],
        tags: ["AlertRule"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function resolve()
    {
    }


    // ----------------------------
    // POST /api/v1/alert-rule/silent and /unsilent
    // ----------------------------
    #[OA\Post(
        path: "/api/v1/alert-rule/silent",
        operationId: "silentAlertRule",
        summary: "Mark alert as silent (accepts userIds / minutes)",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "alertId", type: "string"),
                    new OA\Property(property: "userIds", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "minutes", type: "integer", example: 60),
                ],
                type: "object"
            )
        ),
        tags: ["AlertRule"],
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function silent()
    {
    }


    #[OA\Post(
        path: "/api/v1/alert-rule/unsilent",
        operationId: "unsilentAlertRule",
        summary: "Remove silence",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(type: "object")
        ),
        tags: ["AlertRule"],
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function unsilent()
    {
    }


    // ----------------------------
    // GET /api/v1/alert-rule/filter-endpoints
    // ----------------------------
    #[OA\Get(
        path: "/api/v1/alert-rule/filter-endpoints",
        operationId: "filterEndpoints",
        summary: "Get selectable endpoints for alert rules",
        security: [["bearerAuth" => []]],
        tags: ["AlertRule"],
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function filterEndpoints()
    {
    }


    // ----------------------------
    // GET /api/v1/alert-rule/types
    // ----------------------------
    #[OA\Get(
        path: "/api/v1/alert-rule/types",
        operationId: "getAlertRuleTypes",
        summary: "List available alert rule types",
        tags: ["AlertRule"],
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function getTypes()
    {
    }


    // ----------------------------
    // GET /api/v1/alert-rule/history/{id}
    // ----------------------------
    #[OA\Get(
        path: "/api/v1/alert-rule/history/{id}",
        operationId: "getAlertHistory",
        summary: "Get history for an alert rule",
        security: [["bearerAuth" => []]],
        tags: ["AlertRule"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function history()
    {
    }


    // ----------------------------
    // GET /api/v1/alert-rule/triggered/{id}
    // ----------------------------
    #[OA\Get(
        path: "/api/v1/alert-rule/triggered/{id}",
        operationId: "getTriggeredAlerts",
        summary: "Get triggered/fired alerts for an alert rule",
        security: [["bearerAuth" => []]],
        tags: ["AlertRule"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function firedAlerts()
    {
    }

}
