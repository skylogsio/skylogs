<?php

namespace App\Docs\AlertRule;

use OpenApi\Attributes as OA;

/**
 * OpenAPI component schemas for alert rule create/update payloads.
 * POST /api/v1/alert-rule uses {@see AlertRuleStoreInput} (discriminator: type).
 * Prometheus, Grafana, and PMM variants use a nested discriminator on queryType.
 */
#[OA\Schema(
    schema: 'AlertRuleState',
    description: 'Alert rule state stored in `state` and exposed as `statusLabel` / `status_label` on list and detail responses.',
    type: 'string',
    enum: ['unknown', 'warning', 'critical', 'triggered', 'resolved'],
    example: 'critical'
)]

#[OA\Schema(
    schema: 'AlertRuleStoreCommonFields',
    description: 'Fields shared by every alert rule type on create.',
    properties: [
        new OA\Property(property: 'name', description: 'Unique alert rule name', type: 'string', example: 'High CPU usage'),
        new OA\Property(property: 'description', type: 'string', example: 'Fires when CPU exceeds threshold'),
        new OA\Property(property: 'showAcknowledgeBtn', description: 'Show acknowledge action in notification messages', type: 'boolean', default: false),
        new OA\Property(property: 'isPrivate', description: 'When true, hides the rule from organization-wide readonly listing/detail access', type: 'boolean', default: false),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string'), example: ['production', 'cpu']),
        new OA\Property(property: 'userIds', description: 'Additional users granted access (MongoDB user ids)', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'teamIds', description: 'Teams granted access', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'endpointIds', description: 'Notification endpoints to attach', type: 'array', items: new OA\Items(type: 'string')),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStoreApi',
    title: 'API alert rule',
    description: 'Inbound webhook alert (fire, resolve, status). Server generates `apiToken` after create.',
    required: ['name', 'type'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['api']),
                new OA\Property(property: 'enableAutoResolve', description: 'Automatically resolve firing instances after a period', type: 'boolean', example: true),
                new OA\Property(property: 'autoResolveMinutes', description: 'Minutes until auto-resolve when enableAutoResolve is true', type: 'integer', example: 5),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStoreNotification',
    title: 'Notification alert rule',
    description: 'Receives generic notification webhooks. Server generates `apiToken` after create.',
    required: ['name', 'type'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['notification']),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStorePrometheusDynamic',
    title: 'Prometheus (dynamic)',
    description: 'Match alerts by external rule name, data sources, and optional label filters.',
    required: ['name', 'type', 'queryType', 'dataSourceAlertName'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['prometheus']),
                new OA\Property(property: 'queryType', type: 'string', enum: ['dynamic']),
                new OA\Property(property: 'dataSourceIds', description: 'Prometheus data source ids (may be empty)', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceAlertName', description: 'Alert name in the external Prometheus/Grafana ruler', type: 'string', example: 'HighMemory'),
                new OA\Property(property: 'extraField', description: 'Label key/value filters', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleExtraField')),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStorePrometheusTextQuery',
    title: 'Prometheus (text query)',
    description: 'Evaluate a raw PromQL query object instead of matching external alert names.',
    required: ['name', 'type', 'queryType', 'queryText'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['prometheus']),
                new OA\Property(property: 'queryType', type: 'string', enum: ['textQuery']),
                new OA\Property(property: 'queryText', description: 'PromQL expression string', type: 'string'),
                new OA\Property(property: 'queryObject', description: 'Structured query payload used by the checker', type: 'object'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStorePrometheus',
    description: 'Prometheus alert rule — choose dynamic or textQuery variant.',
    oneOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStorePrometheusDynamic'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStorePrometheusTextQuery'),
    ],
    discriminator: new OA\Discriminator(
        propertyName: 'queryType',
        mapping: [
            'dynamic' => '#/components/schemas/AlertRuleStorePrometheusDynamic',
            'textQuery' => '#/components/schemas/AlertRuleStorePrometheusTextQuery',
        ]
    )
)]

#[OA\Schema(
    schema: 'AlertRuleStoreGrafanaDynamic',
    title: 'Grafana (dynamic)',
    required: ['name', 'type', 'queryType', 'dataSourceAlertName'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['grafana']),
                new OA\Property(property: 'queryType', type: 'string', enum: ['dynamic']),
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceAlertName', type: 'string'),
                new OA\Property(property: 'extraField', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleExtraField')),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStoreGrafanaTextQuery',
    title: 'Grafana (text query)',
    required: ['name', 'type', 'queryType', 'queryText'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['grafana']),
                new OA\Property(property: 'queryType', type: 'string', enum: ['textQuery']),
                new OA\Property(property: 'queryText', type: 'string'),
                new OA\Property(property: 'queryObject', type: 'object'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStoreGrafana',
    description: 'Grafana alert rule — choose dynamic or textQuery variant.',
    oneOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreGrafanaDynamic'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreGrafanaTextQuery'),
    ],
    discriminator: new OA\Discriminator(
        propertyName: 'queryType',
        mapping: [
            'dynamic' => '#/components/schemas/AlertRuleStoreGrafanaDynamic',
            'textQuery' => '#/components/schemas/AlertRuleStoreGrafanaTextQuery',
        ]
    )
)]

#[OA\Schema(
    schema: 'AlertRuleStorePmmDynamic',
    title: 'PMM (dynamic)',
    required: ['name', 'type', 'queryType', 'dataSourceAlertName'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['pmm']),
                new OA\Property(property: 'queryType', type: 'string', enum: ['dynamic']),
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceAlertName', type: 'string'),
                new OA\Property(property: 'extraField', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleExtraField')),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStorePmmTextQuery',
    title: 'PMM (text query)',
    required: ['name', 'type', 'queryType', 'queryText'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['pmm']),
                new OA\Property(property: 'queryType', type: 'string', enum: ['textQuery']),
                new OA\Property(property: 'queryText', type: 'string'),
                new OA\Property(property: 'queryObject', type: 'object'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStorePmm',
    description: 'Percona PMM alert rule — choose dynamic or textQuery variant.',
    oneOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStorePmmDynamic'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStorePmmTextQuery'),
    ],
    discriminator: new OA\Discriminator(
        propertyName: 'queryType',
        mapping: [
            'dynamic' => '#/components/schemas/AlertRuleStorePmmDynamic',
            'textQuery' => '#/components/schemas/AlertRuleStorePmmTextQuery',
        ]
    )
)]

#[OA\Schema(
    schema: 'AlertRuleStoreSentry',
    title: 'Sentry alert rule',
    description: 'Webhook-driven Sentry issue alerts.',
    required: ['name', 'type', 'dataSourceIds', 'dataSourceAlertName'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['sentry']),
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceAlertName', description: 'Sentry project or alert identifier configured in Skylogs', type: 'string'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStoreSplunk',
    title: 'Splunk alert rule',
    required: ['name', 'type', 'dataSourceIds', 'dataSourceAlertName'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['splunk']),
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceAlertName', type: 'string'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStoreMetabase',
    title: 'Metabase alert rule',
    required: ['name', 'type', 'dataSourceIds', 'dataSourceAlertName'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['metabase']),
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceAlertName', type: 'string'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStoreZabbix',
    title: 'Zabbix alert rule',
    description: 'Filter Zabbix webhooks by hosts, actions, and severities (0–5 as strings, or omit for all).',
    required: ['name', 'type', 'dataSourceIds'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['zabbix']),
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'hosts', type: 'array', items: new OA\Items(type: 'string'), example: ['web-01']),
                new OA\Property(property: 'actions', type: 'array', items: new OA\Items(type: 'string'), example: ['Action1']),
                new OA\Property(
                    property: 'severities',
                    description: 'Zabbix severity codes 0 (not classified) through 5 (disaster)',
                    type: 'array',
                    items: new OA\Items(type: 'string', enum: ['0', '1', '2', '3', '4', '5']),
                    example: ['5']
                ),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStoreElastic',
    title: 'Elastic alert rule',
    description: 'Document-count threshold on an Elastic data view.',
    required: ['name', 'type', 'dataSourceId', 'dataviewName', 'dataviewTitle', 'queryString', 'minutes', 'conditionType', 'countDocument'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['elastic']),
                new OA\Property(property: 'dataSourceId', type: 'string'),
                new OA\Property(property: 'dataviewName', type: 'string', example: 'responses'),
                new OA\Property(property: 'dataviewTitle', type: 'string', example: 'responses*'),
                new OA\Property(property: 'queryString', type: 'string', example: 'OriginStatus:>=400'),
                new OA\Property(property: 'minutes', description: 'Look-back window in minutes', type: 'integer', example: 15),
                new OA\Property(property: 'conditionType', type: 'string', enum: ['greaterOrEqual', 'lessOrEqual']),
                new OA\Property(property: 'countDocument', description: 'Document count threshold', type: 'integer', example: 5),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStoreVictoriaLogs',
    title: 'VictoriaLogs alert rule',
    description: 'Log line count threshold on a VictoriaLogs data source.',
    required: ['name', 'type', 'dataSourceId', 'queryString', 'minutes', 'conditionType', 'countDocument'],
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['victoria_logs']),
                new OA\Property(property: 'dataSourceId', type: 'string'),
                new OA\Property(property: 'queryString', type: 'string'),
                new OA\Property(property: 'minutes', type: 'integer', example: 15),
                new OA\Property(property: 'conditionType', type: 'string', enum: ['greaterOrEqual', 'lessOrEqual']),
                new OA\Property(property: 'countDocument', type: 'integer', example: 5),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleStoreInput',
    description: 'Create alert rule. The `type` field selects the payload shape (same URL for all types).',
    discriminator: new OA\Discriminator(
        propertyName: 'type',
        mapping: [
            'api' => '#/components/schemas/AlertRuleStoreApi',
            'notification' => '#/components/schemas/AlertRuleStoreNotification',
            'prometheus' => '#/components/schemas/AlertRuleStorePrometheus',
            'grafana' => '#/components/schemas/AlertRuleStoreGrafana',
            'pmm' => '#/components/schemas/AlertRuleStorePmm',
            'sentry' => '#/components/schemas/AlertRuleStoreSentry',
            'splunk' => '#/components/schemas/AlertRuleStoreSplunk',
            'metabase' => '#/components/schemas/AlertRuleStoreMetabase',
            'zabbix' => '#/components/schemas/AlertRuleStoreZabbix',
            'elastic' => '#/components/schemas/AlertRuleStoreElastic',
            'victoria_logs' => '#/components/schemas/AlertRuleStoreVictoriaLogs',
        ]
    ),
    oneOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreApi'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreNotification'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStorePrometheus'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreGrafana'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStorePmm'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreSentry'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreSplunk'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreMetabase'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreZabbix'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreElastic'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleStoreVictoriaLogs'),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateCommonFields',
    description: 'Fields that can be updated on any alert rule type. `isPrivate` is applied only when the caller has admin access on the rule.',
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'showAcknowledgeBtn', type: 'boolean'),
        new OA\Property(property: 'isPrivate', description: 'When true, hides the rule from organization-wide readonly access. Only applied for callers with admin access.', type: 'boolean'),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'userIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'teamIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'endpointIds', type: 'array', items: new OA\Items(type: 'string')),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateApi',
    title: 'Update API alert rule',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'enableAutoResolve', type: 'boolean'),
                new OA\Property(property: 'autoResolveMinutes', type: 'integer'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateNotification',
    title: 'Update notification alert rule',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdatePrometheusDynamic',
    title: 'Update Prometheus (dynamic)',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'queryType', type: 'string', enum: ['dynamic']),
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceAlertName', type: 'string'),
                new OA\Property(property: 'extraField', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleExtraField')),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdatePrometheusTextQuery',
    title: 'Update Prometheus (text query)',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'queryType', type: 'string', enum: ['textQuery']),
                new OA\Property(property: 'queryText', type: 'string'),
                new OA\Property(property: 'queryObject', type: 'object'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdatePrometheus',
    oneOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdatePrometheusDynamic'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdatePrometheusTextQuery'),
    ],
    discriminator: new OA\Discriminator(
        propertyName: 'queryType',
        mapping: [
            'dynamic' => '#/components/schemas/AlertRuleUpdatePrometheusDynamic',
            'textQuery' => '#/components/schemas/AlertRuleUpdatePrometheusTextQuery',
        ]
    )
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateGrafanaDynamic',
    title: 'Update Grafana (dynamic)',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'queryType', type: 'string', enum: ['dynamic']),
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceAlertName', type: 'string'),
                new OA\Property(property: 'extraField', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleExtraField')),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateGrafanaTextQuery',
    title: 'Update Grafana (text query)',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'queryType', type: 'string', enum: ['textQuery']),
                new OA\Property(property: 'queryText', type: 'string'),
                new OA\Property(property: 'queryObject', type: 'object'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateGrafana',
    oneOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateGrafanaDynamic'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateGrafanaTextQuery'),
    ],
    discriminator: new OA\Discriminator(
        propertyName: 'queryType',
        mapping: [
            'dynamic' => '#/components/schemas/AlertRuleUpdateGrafanaDynamic',
            'textQuery' => '#/components/schemas/AlertRuleUpdateGrafanaTextQuery',
        ]
    )
)]

#[OA\Schema(
    schema: 'AlertRuleUpdatePmmDynamic',
    title: 'Update PMM (dynamic)',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'queryType', type: 'string', enum: ['dynamic']),
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceAlertName', type: 'string'),
                new OA\Property(property: 'extraField', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleExtraField')),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdatePmmTextQuery',
    title: 'Update PMM (text query)',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'queryType', type: 'string', enum: ['textQuery']),
                new OA\Property(property: 'queryText', type: 'string'),
                new OA\Property(property: 'queryObject', type: 'object'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdatePmm',
    oneOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdatePmmDynamic'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdatePmmTextQuery'),
    ],
    discriminator: new OA\Discriminator(
        propertyName: 'queryType',
        mapping: [
            'dynamic' => '#/components/schemas/AlertRuleUpdatePmmDynamic',
            'textQuery' => '#/components/schemas/AlertRuleUpdatePmmTextQuery',
        ]
    )
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateSentry',
    title: 'Update Sentry alert rule',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceAlertName', type: 'string'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateSplunk',
    title: 'Update Splunk alert rule',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceAlertName', type: 'string'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateMetabase',
    title: 'Update Metabase alert rule',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'dataSourceAlertName', type: 'string'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateZabbix',
    title: 'Update Zabbix alert rule',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'dataSourceIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'hosts', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'actions', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'severities', type: 'array', items: new OA\Items(type: 'string', enum: ['0', '1', '2', '3', '4', '5'])),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateElastic',
    title: 'Update Elastic alert rule',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'dataSourceId', type: 'string'),
                new OA\Property(property: 'dataviewName', type: 'string'),
                new OA\Property(property: 'dataviewTitle', type: 'string'),
                new OA\Property(property: 'queryString', type: 'string'),
                new OA\Property(property: 'minutes', type: 'integer'),
                new OA\Property(property: 'conditionType', type: 'string', enum: ['greaterOrEqual', 'lessOrEqual']),
                new OA\Property(property: 'countDocument', type: 'integer'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateVictoriaLogs',
    title: 'Update VictoriaLogs alert rule',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateCommonFields'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'dataSourceId', type: 'string'),
                new OA\Property(property: 'queryString', type: 'string'),
                new OA\Property(property: 'minutes', type: 'integer'),
                new OA\Property(property: 'conditionType', type: 'string', enum: ['greaterOrEqual', 'lessOrEqual']),
                new OA\Property(property: 'countDocument', type: 'integer'),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleUpdateInput',
    description: 'Update alert rule. Send the variant that matches the rule\'s existing type (type cannot be changed via this endpoint).',
    oneOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateApi'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateNotification'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdatePrometheus'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateGrafana'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdatePmm'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateSentry'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateSplunk'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateMetabase'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateZabbix'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateElastic'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleUpdateVictoriaLogs'),
    ]
)]

class AlertRuleSchemasDoc {}
