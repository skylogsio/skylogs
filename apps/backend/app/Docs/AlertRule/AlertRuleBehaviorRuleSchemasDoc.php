<?php

namespace App\Docs\AlertRule;

use OpenApi\Attributes as OA;

/**
 * OpenAPI schemas for alert rule behavior rules.
 *
 * POST /api/v1/alert-rule-behavior-rule/{alertRuleId} uses {@see AlertRuleBehaviorRuleStoreInput}
 * (discriminator: type). PUT uses {@see AlertRuleBehaviorRuleUpdateInput} — send only fields
 * allowed for the existing rule type (type cannot be changed on update).
 */
#[OA\Tag(
    name: 'AlertRule Behavior Rules',
    description: <<<'DESC'
Optional rules attached to an alert rule that change notification behavior.

| Type | Purpose |
|------|---------|
| `notification` | Add extra endpoints when incoming alert labels match filters |
| `template` | Override message text for specific endpoints |
| `silent` | Suppress all notifications while another alert rule is `resolved` or `critical` |

**Create:** one URL — set `type` and send the matching body (see discriminator on POST).

**Silence on the list page:** `isSilent` is the per-user manual toggle (`POST /api/v1/alert-rule/silent/{id}`).
`isSilentByBehavior` is computed from silent behavior rules and is read-only in the UI.
DESC
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleEndpoint',
    description: 'Endpoint reference included in behavior rule API responses.',
    required: ['id', 'name'],
    properties: [
        new OA\Property(property: 'id', description: 'Endpoint MongoDB `_id`', type: 'string', pattern: '^[0-9a-fA-F]{24}$'),
        new OA\Property(property: 'name', description: 'Endpoint display name', type: 'string', example: 'Ops email'),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleFilter',
    description: 'Label or annotation filter. Values support wildcards (e.g. `mysql*`). All filters must match.',
    required: ['key', 'value'],
    properties: [
        new OA\Property(property: 'key', description: 'Label or annotation key. For API alerts use `instance`.', type: 'string', example: 'db_name'),
        new OA\Property(property: 'value', description: 'Pattern to match against the alert value', type: 'string', example: 'mysql*'),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleNotification',
    title: 'Notification behavior rule',
    description: 'Adds the listed endpoints when every filter matches the firing alert.',
    required: ['id', 'name', 'type', 'filters', 'endpointIds', 'endpoints'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'),
        new OA\Property(property: 'name', description: 'Display name for this behavior rule', type: 'string', example: 'MySQL production endpoints'),
        new OA\Property(property: 'type', type: 'string', enum: ['notification']),
        new OA\Property(property: 'filters', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleBehaviorRuleFilter')),
        new OA\Property(property: 'endpointIds', description: 'Extra endpoints to notify (in addition to the alert rule defaults)', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'endpoints', description: 'Resolved endpoint id and name pairs for `endpointIds`', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleBehaviorRuleEndpoint')),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleTemplate',
    title: 'Template behavior rule',
    description: <<<'DESC'
Uses a custom template string for messages sent to the listed endpoints.

**Prometheus, Grafana, and PMM** support type-aware placeholders:

| Placeholder | Description |
|-------------|-------------|
| `{{name}}` | Alert rule name |
| `{{state}}` / `{{state_line}}` | Rule state value / formatted state line |
| `{{fireCount}}` | Number of firing alerts |
| `{{date}}` | Jalali date line (`date:` or `Date:` prefix included) |
| `{{dataSourceName}}` | Data source name |
| `{{label.KEY}}` | Label from first firing alert |
| `{{annotation.KEY}}` | Annotation from first firing alert |
| `{{severity_line}}` | Severity line with emoji |
| `{{labels:alertname,pod}}` | Label block (include only listed keys) |
| `{{labels:* exclude=job}}` | All labels except listed keys |
| `{{annotations:summary,description}}` | Annotation block (include) |
| `{{alert_items labels="pod" annotations="summary"}}` | Per-alert section |
| `{{alert_items labels="*" exclude_labels="job"}}` | Per-alert section with exclusions |

**Default Prometheus template:**
```
{{name}}

{{state_line}}
{{alert_items labels="alertname,namespace,pod,reason,severity,job" annotations="summary,description"}}
{{date}}
```

**Default Grafana / PMM template:**
```
{{name}}

{{state_line}}
Data Source: {{dataSourceName}}

{{alert_items labels="*" annotations="summary,description"}}
{{date}}
```

Other alert types fall back to generic `{{name}}`, `{{state}}`, and dotted `{{alert.*}}` paths.
DESC,
    required: ['id', 'name', 'type', 'endpointIds', 'endpoints', 'template'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', description: 'Display name for this behavior rule', type: 'string', example: 'Disk alert template'),
        new OA\Property(property: 'type', type: 'string', enum: ['template']),
        new OA\Property(property: 'endpointIds', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'endpoints', description: 'Resolved endpoint id and name pairs for `endpointIds`', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertRuleBehaviorRuleEndpoint')),
        new OA\Property(
            property: 'template',
            type: 'string',
            example: "{{name}}\n\n{{state_line}}\n{{alert_items labels=\"pod,namespace\" annotations=\"summary\"}}\ndate: {{date}}",
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleSilent',
    title: 'Silent behavior rule',
    description: 'While any listed alert rule has the given `triggerState`, notifications for this alert rule are suppressed. Does not affect the manual `isSilent` toggle.',
    required: ['id', 'name', 'type', 'dependsOnAlertRuleIds', 'triggerState'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', description: 'Display name for this behavior rule', type: 'string', example: 'Silence during maintenance'),
        new OA\Property(property: 'type', type: 'string', enum: ['silent']),
        new OA\Property(
            property: 'dependsOnAlertRuleIds',
            description: 'MongoDB `_id` values of other alert rules to watch',
            type: 'array',
            items: new OA\Items(type: 'string', pattern: '^[0-9a-fA-F]{24}$'),
            example: ['507f1f77bcf86cd799439011']
        ),
        new OA\Property(
            property: 'triggerState',
            description: 'When any dependent rule reaches this status, this alert becomes silent',
            type: 'string',
            enum: ['resolved', 'critical'],
            example: 'resolved'
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRule',
    description: 'A behavior rule returned from list, create, update, or alert rule detail (`rules` array). Shape depends on `type`.',
    discriminator: new OA\Discriminator(
        propertyName: 'type',
        mapping: [
            'notification' => '#/components/schemas/AlertRuleBehaviorRuleNotification',
            'template' => '#/components/schemas/AlertRuleBehaviorRuleTemplate',
            'silent' => '#/components/schemas/AlertRuleBehaviorRuleSilent',
        ]
    ),
    oneOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleBehaviorRuleNotification'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleBehaviorRuleTemplate'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleBehaviorRuleSilent'),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleStoreNotification',
    title: 'Create notification rule',
    required: ['name', 'type', 'filters', 'endpointIds'],
    properties: [
        new OA\Property(property: 'name', description: 'Display name for this behavior rule', type: 'string', minLength: 1, maxLength: 255, example: 'MySQL production endpoints'),
        new OA\Property(property: 'type', type: 'string', enum: ['notification']),
        new OA\Property(property: 'filters', type: 'array', minItems: 1, items: new OA\Items(ref: '#/components/schemas/AlertRuleBehaviorRuleFilter')),
        new OA\Property(property: 'endpointIds', type: 'array', minItems: 1, items: new OA\Items(type: 'string')),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleStoreTemplate',
    title: 'Create template rule',
    required: ['name', 'type', 'endpointIds', 'template'],
    properties: [
        new OA\Property(property: 'name', description: 'Display name for this behavior rule', type: 'string', minLength: 1, maxLength: 255, example: 'Disk alert template'),
        new OA\Property(property: 'type', type: 'string', enum: ['template']),
        new OA\Property(property: 'endpointIds', type: 'array', minItems: 1, items: new OA\Items(type: 'string')),
        new OA\Property(
            property: 'template',
            description: 'Message template. See `AlertRuleBehaviorRuleTemplate` for Prometheus/Grafana/PMM placeholder syntax.',
            type: 'string',
            minLength: 1,
            example: "{{name}}\n\n{{state_line}}\n{{alert_items labels=\"pod\" annotations=\"summary\"}}",
        ),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleStoreSilent',
    title: 'Create silent rule',
    required: ['name', 'type', 'dependsOnAlertRuleIds', 'triggerState'],
    properties: [
        new OA\Property(property: 'name', description: 'Display name for this behavior rule', type: 'string', minLength: 1, maxLength: 255, example: 'Silence during maintenance'),
        new OA\Property(property: 'type', type: 'string', enum: ['silent']),
        new OA\Property(property: 'dependsOnAlertRuleIds', type: 'array', minItems: 1, items: new OA\Items(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        new OA\Property(property: 'triggerState', type: 'string', enum: ['resolved', 'critical']),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleStoreInput',
    description: 'Create a behavior rule. Set `type` to pick the payload shape (same URL for all types). Requires alert rule admin access.',
    discriminator: new OA\Discriminator(
        propertyName: 'type',
        mapping: [
            'notification' => '#/components/schemas/AlertRuleBehaviorRuleStoreNotification',
            'template' => '#/components/schemas/AlertRuleBehaviorRuleStoreTemplate',
            'silent' => '#/components/schemas/AlertRuleBehaviorRuleStoreSilent',
        ]
    ),
    oneOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleBehaviorRuleStoreNotification'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleBehaviorRuleStoreTemplate'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleBehaviorRuleStoreSilent'),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleUpdateNotification',
    title: 'Update notification rule',
    description: 'Only `name`, `filters`, and `endpointIds` can be changed. `type` is fixed after create.',
    properties: [
        new OA\Property(property: 'name', type: 'string', minLength: 1, maxLength: 255),
        new OA\Property(property: 'filters', type: 'array', minItems: 1, items: new OA\Items(ref: '#/components/schemas/AlertRuleBehaviorRuleFilter')),
        new OA\Property(property: 'endpointIds', type: 'array', minItems: 1, items: new OA\Items(type: 'string')),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleUpdateTemplate',
    title: 'Update template rule',
    properties: [
        new OA\Property(property: 'name', type: 'string', minLength: 1, maxLength: 255),
        new OA\Property(property: 'endpointIds', type: 'array', minItems: 1, items: new OA\Items(type: 'string')),
        new OA\Property(property: 'template', type: 'string', minLength: 1),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleSelectableAlert',
    description: 'Alert rule that can be selected as a silent-rule dependency (status supports resolved or critical).',
    required: ['id', 'name', 'type', 'state'],
    properties: [
        new OA\Property(property: 'id', description: 'Alert rule MongoDB `_id`', type: 'string', pattern: '^[0-9a-fA-F]{24}$'),
        new OA\Property(property: 'name', description: 'Alert rule display name', type: 'string', example: 'MySQL replication lag'),
        new OA\Property(property: 'type', description: 'Alert rule type', type: 'string', example: 'prometheus'),
        new OA\Property(property: 'state', description: 'Current status from `getStatus()`', type: 'string', enum: ['unknown', 'warning', 'critical', 'triggered', 'resolved']),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleUpdateSilent',
    title: 'Update silent rule',
    description: '`filters`, `endpointIds`, and `template` are not allowed on silent rules.',
    properties: [
        new OA\Property(property: 'name', type: 'string', minLength: 1, maxLength: 255),
        new OA\Property(property: 'dependsOnAlertRuleIds', type: 'array', minItems: 1, items: new OA\Items(type: 'string', pattern: '^[0-9a-fA-F]{24}$')),
        new OA\Property(property: 'triggerState', type: 'string', enum: ['resolved', 'critical']),
    ]
)]

#[OA\Schema(
    schema: 'AlertRuleBehaviorRuleUpdateInput',
    description: 'Update a behavior rule. Send the variant that matches the rule\'s existing type (identified by `ruleId` in the path).',
    oneOf: [
        new OA\Schema(ref: '#/components/schemas/AlertRuleBehaviorRuleUpdateNotification'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleBehaviorRuleUpdateTemplate'),
        new OA\Schema(ref: '#/components/schemas/AlertRuleBehaviorRuleUpdateSilent'),
    ]
)]

class AlertRuleBehaviorRuleSchemasDoc {}
