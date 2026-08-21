# Incident frontend flows

All endpoints are under `/api/v1` and need:

```
Authorization: Bearer <token>
```

`{id}` is always a 24-character hex MongoDB id.

`status` on an incident is **not writable**. It only changes through acknowledge / resolve (or by sending `resolvedAt` on create/update).

```
open ── first ack ──► investigating ── resolve ──► resolved
  └────────────────── resolve ─────────────────────┘
```

---

## 1. Manual incident CRUD

Incidents created here always have `source: "manual"`. The policy engine does **not** auto-create incidents yet.

```
List  GET /incident
  │
  ├─ Create  POST /incident  ──► 201  { data: incident }
  │              │
  │              ├─ optional resolvedAt ──► created as resolved
  │              ├─ optional postMortem ──► upserts the postmortem
  │              └─ optional documents  ──► additive file / URL attachments
  │
  ├─ Show    GET /incident/{id}     ──► { data: incident }
  ├─ Update  PUT /incident/{id}     ──► { data: incident }   (full replace)
  ├─ Delete  DELETE /incident/{id}  ──► { status: true }     (also deletes docs)
  └─ Resolve POST /incident/{id}/resolve  ──► { data: incident }
```

### List

`GET /api/v1/incident`

| Query | Notes |
| --- | --- |
| `page`, `perPage` | Default 25, max 100 |
| `status` | `open` \| `investigating` \| `resolved` |
| `severity` | `SEV1`–`SEV4` |
| `teamId` | Exact team |
| `tag` | Exact tag |
| `search` | Partial match on **title** only |

Sorted by `startedAt` desc. Pagination is the same shape as endpoints, users, and teams: `current_page`, `data`, `last_page`, `per_page`, `total` at the top level (snake_case). Incident fields are camelCase.

### Create

`POST /api/v1/incident` → `201`

```json
{
  "title": "Checkout latency spike",
  "description": "p99 above 4s for 20 minutes",
  "severity": "SEV2",
  "teamIds": ["66a1c0de5f1a2b3c4d5e6f70"],
  "tags": ["payments", "latency"],
  "startedAt": "2026-08-20T04:10:00Z",
  "detectedAt": "2026-08-20T04:14:00Z",
  "alertRuleIds": ["66a1c0de5f1a2b3c4d5e6f71"]
}
```

Required: `title`, `severity`, `teamIds` (at least one; caller must belong to **all** of them).

Optional: `description`, `tags`, `startedAt` (default now), `detectedAt` (default now), `resolvedAt`, `alertRuleIds`, `postMortem`, `documents`.

Send `resolvedAt` to log a past, already-closed incident (`status: "resolved"`).

`postMortem` uses the same body as `PUT /incident/{id}/postmortem` (`summary` required when the object is sent). `documents` is an array of the same items as `POST /incident/{id}/document` (exactly one of `file` or `externalUrl`). JSON is enough for links; file uploads need `multipart/form-data` (`documents[0][file]`). Documents with `attachableType: "postMortem"` are allowed in the same request because the postmortem is written first.

Create/update responses include the `postMortem` summary and `counts`, same as show. Dedicated nested routes still work and remain the path for listing, download URLs, delete, and publish.

```json
{
  "title": "Checkout latency spike",
  "severity": "SEV2",
  "teamIds": ["66a1c0de5f1a2b3c4d5e6f70"],
  "postMortem": {
    "summary": "Payment pool saturation caused checkout 5xx for 22 minutes."
  },
  "documents": [
    {
      "externalUrl": "https://grafana.example.com/d/checkout",
      "name": "Checkout dashboard",
      "type": "metric"
    }
  ]
}
```

### Show / update / delete / resolve

`GET /api/v1/incident/{id}`

`PUT /api/v1/incident/{id}` — **full replace** of the incident fields. Omit `description` / `tags` / `alertRuleIds` and they are cleared. Pre-fill the form from show and submit the whole object.

Nested documentation is **not** a full replace: omit `postMortem` to leave the existing postmortem unchanged; `documents` only **adds** attachments (delete still uses `DELETE /incident/{id}/document/{docId}`). Nested `postMortem` / `documents` are allowed only when `source` is `manual`; a policy incident returns `422`. File uploads on update use the same multipart fields as create.

```json
{
  "title": "Checkout latency spike",
  "description": "Updated notes",
  "severity": "SEV2",
  "teamIds": ["66a1c0de5f1a2b3c4d5e6f70"],
  "tags": ["payments"],
  "startedAt": "2026-08-20T04:10:00Z",
  "detectedAt": "2026-08-20T04:14:00Z",
  "alertRuleIds": []
}
```

`DELETE /api/v1/incident/{id}` → `{ "status": true }`  
Also deletes postmortem, timeline, documents (files included), and action items.

`POST /api/v1/incident/{id}/resolve`

```json
{ "resolvedAt": "2026-08-20T05:02:00Z" }
```

`resolvedAt` is optional (defaults to now). Already-resolved → `403`. Gate the button on `canResolve`.

### Incident object (response `data`)

```json
{
  "id": "66c1c0de5f1a2b3c4d5e6f70",
  "title": "Checkout latency spike",
  "description": "p99 above 4s for 20 minutes",
  "severity": "SEV2",
  "status": "open",
  "source": "manual",
  "startedAt": "2026-08-20T04:10:00Z",
  "detectedAt": "2026-08-20T04:14:00Z",
  "resolvedAt": null,
  "teamIds": ["66a1c0de5f1a2b3c4d5e6f70"],
  "tags": ["payments"],
  "alertRuleIds": ["66a1c0de5f1a2b3c4d5e6f71"],
  "createdBy": "66a2c0de5f1a2b3c4d5e6f80",
  "createdByUser": { "id": "66a2...", "name": "Ada" },
  "resolvedBy": null,
  "acknowledgements": [],
  "teams": [
    {
      "id": "66a1c0de5f1a2b3c4d5e6f70",
      "name": "payments",
      "onCallPlan": { "id": "66b1...", "name": "payments-primary" },
      "acknowledgement": null
    }
  ],
  "alertRules": [{ "id": "66a1...", "name": "payments-latency-p99" }],
  "postMortem": null,
  "counts": {
    "timelineEntries": 1,
    "documents": 0,
    "actionItems": 0,
    "openActionItems": 0
  },
  "canEdit": true,
  "canDelete": true,
  "canAcknowledge": true,
  "canResolve": true,
  "createdAt": "2026-08-20T04:14:10Z",
  "updatedAt": "2026-08-20T04:14:10Z"
}
```

`counts` is on **show only** (`null` on list). Bind buttons to `can*` flags — do not recompute permissions.

---

## 2. Policy API CRUD

Policies are **config only**. Saving and reading works. Nothing auto-creates incidents, enforces SLA, or escalates yet.

Two write doors, same stored document:

- JSON form → `source: "api"`
- YAML import → `source: "yaml"`

```
List     GET  /incident-policy
Show     GET  /incident-policy/{id}
Create   POST /incident-policy          JSON body, source=api
Update   PUT  /incident-policy/{id}     JSON body, source=api, version++
Delete   DELETE /incident-policy/{id}

YAML (optional, same object):
  Validate  POST /incident-policy/validate
  Import    POST /incident-policy/import
  Export    GET  /incident-policy/{id}/export   → raw YAML
```

Write (create / update / import / delete) needs **owner** or **manager**. List / show / export is team members.

### List / show

`GET /api/v1/incident-policy?page=1&perPage=25&enabled=true&teamId=...&search=payments`

`GET /api/v1/incident-policy/{id}` → `{ "data": { ...policy } }`

### Create (JSON)

`POST /api/v1/incident-policy` → `201`

Required: `name` (slug, unique), `teamIds`, `match` (at least one matcher), `rules` (map keyed by severity, at least one).

```json
{
  "name": "payments-critical",
  "description": "Response policy for payment-path incidents",
  "enabled": true,
  "ownerId": "66a2c0de5f1a2b3c4d5e6f80",
  "teamIds": ["66a1c0de5f1a2b3c4d5e6f70"],
  "match": {
    "alertRuleIds": ["66a1c0de5f1a2b3c4d5e6f71"],
    "tags": ["payments", "tier-1"],
    "serviceIds": [],
    "dataSourceTypes": ["prometheus"]
  },
  "grouping": {
    "key": ["serviceId", "alertRuleId"],
    "windowMinutes": 15
  },
  "incident": {
    "autoCreate": true,
    "autoResolveOnAlertClear": false,
    "titleTemplate": "{{ alert.name }} on {{ service.name }}",
    "defaultSeverity": "SEV3",
    "severityMap": { "critical": "SEV1", "warning": "SEV3" }
  },
  "rules": {
    "SEV1": {
      "ackWithinMinutes": 5,
      "resolveWithinMinutes": 60,
      "requireCommander": true,
      "notifyEndpointIds": ["66b1c0de5f1a2b3c4d5e6f71"],
      "escalation": { "onCallPlanId": "66b2c0de5f1a2b3c4d5e6f72", "useLayers": true },
      "communication": { "stakeholderUpdateEveryMinutes": 30, "statusPageUpdateRequired": true },
      "postmortem": { "required": true, "dueDays": 5, "reviewRequired": true },
      "runbookNames": ["payments-api-5xx-triage"]
    }
  }
}
```

JSON uses **ids**. YAML import uses **names**. `rules` is a map (`SEV1`…`SEV4`), not an array. Partial coverage is valid (SEV1 only is fine).

`runbookNames` stay as names even if no runbook exists yet. Response also has read-only `runbookIds` for names that resolved. Do **not** pair the two arrays by index.

### Update (JSON)

`PUT /api/v1/incident-policy/{id}` — same body as create. Full replace. Always bumps `version` (unlike YAML import, which leaves version alone when unchanged).

### Delete

`DELETE /api/v1/incident-policy/{id}` → `{ "status": true }`

### YAML import (optional)

`POST /api/v1/incident-policy/import`

JSON string:

```json
{
  "yaml": "apiVersion: skylogs.io/v1\nkind: IncidentPolicy\nmetadata:\n  name: payments-critical\n  teams: [payments]\nspec:\n  match:\n    tags: [payments]\n  rules:\n    - severity: SEV1\n      ack: { withinMinutes: 5 }\n",
  "dryRun": false
}
```

Or multipart: `file` (`.yaml` / `.yml`, max 512 KB) + optional `dryRun`.

`POST /api/v1/incident-policy/validate` — same body, never writes.

Success / DSL error shape:

```json
{
  "valid": true,
  "dryRun": false,
  "created": [{ "name": "payments-critical", "id": "66c3...", "version": 1 }],
  "updated": [],
  "unchanged": [],
  "errors": []
}
```

`422` with a `valid` key = YAML content errors (`path` + `message`). `422` without `valid` = Laravel field errors (missing file, etc.).

`GET /api/v1/incident-policy/{id}/export` → raw YAML (`application/x-yaml`), not JSON.

---

## 3. Runbooks, RCA, postmortem, documentation

Runbooks are a library (not nested under an incident). Everything else hangs off one incident.

```
Runbook library
  GET/POST          /runbook
  GET/PUT/DELETE    /runbook/{id}

Incident {id}
  │
  ├─ Timeline
  │     GET/POST    /incident/{id}/timeline          (system also writes on create/ack/resolve)
  │
  ├─ Documents
  │     GET/POST    /incident/{id}/document
  │     GET         /incident/{id}/document/{docId}/download-url
  │     DELETE      /incident/{id}/document/{docId}
  │
  ├─ Postmortem + RCA  (one per incident)
  │     GET         /incident/{id}/postmortem        data may be null
  │     PUT         /incident/{id}/postmortem        create or replace
  │     POST        /incident/{id}/postmortem/publish
  │
  └─ Action items
        GET/POST    /incident/{id}/action-item
        PUT/DELETE  /incident/{id}/action-item/{itemId}
        GET         /incident-action-item            cross-incident backlog
```

Read if you can view the incident. Write if `canEdit` on the incident. Runbook create needs owner/manager.

### Runbooks

`POST /api/v1/runbook` → `201`

`sourceType` picks the body: `steps` | `markdown` | `externalUrl`.

Steps:

```json
{
  "name": "Payments API 5xx triage",
  "slug": "payments-api-5xx-triage",
  "description": "First 15 minutes",
  "teamIds": ["66a1c0de5f1a2b3c4d5e6f70"],
  "tags": ["payments"],
  "status": "published",
  "sourceType": "steps",
  "steps": [
    {
      "title": "Check 5xx rate",
      "description": "Open the checkout dashboard",
      "command": "curl -s https://grafana.example.com/d/checkout",
      "expectedResult": "5xx below 1%"
    }
  ],
  "appliesTo": {
    "serviceIds": [],
    "alertRuleIds": ["66a1c0de5f1a2b3c4d5e6f71"],
    "tags": ["payments"],
    "severities": ["SEV1", "SEV2"]
  },
  "reviewIntervalDays": 90
}
```

Markdown: `"sourceType": "markdown", "content": "# Drain traffic\n..."`.  
External: `"sourceType": "externalUrl", "externalUrl": "https://wiki.example.com/runbooks/payments"`.

`PUT` same body (full replace, `version++`). Changing `sourceType` drops the unused body. Slug is derived from `name` if omitted; collisions get `-2`.

`GET /api/v1/runbook?status=published&teamId=...&tag=payments&search=5xx`

### Timeline

Auto-written (`source: "system"`): create, ack, status change, resolve, postmortem publish.

Responder types only on POST: `note`, `action`, `detection`, `mitigation`, `escalation`, `communication`.

`POST /api/v1/incident/{id}/timeline` → `201`

```json
{
  "type": "note",
  "message": "Traffic drained to the secondary region.",
  "occurredAt": "2026-08-20T04:25:00Z",
  "isPublic": true,
  "meta": { "region": "eu-west-1" }
}
```

`occurredAt` defaults to now; may be backdated (entry lands mid-list). Re-fetch after create.

`GET /api/v1/incident/{id}/timeline?type=note&source=user&isPublic=1`  
Ordered by `occurredAt` ascending.

### Documents

Exactly one of `file` or `externalUrl`. Max 20 MB. Send `Accept: application/json` on multipart or a `422` becomes a `302`.

Upload (`multipart/form-data`):

```
file: checkout-errors.png
type: screenshot
description: Error rate at the peak
attachableType: incident
```

External link (`application/json`):

```json
{
  "externalUrl": "https://grafana.example.com/d/checkout",
  "name": "Checkout dashboard",
  "type": "metric",
  "attachableType": "incident"
}
```

`type`: `screenshot` | `log` | `metric` | `diagram` | `report` | `other`.  
`attachableType`: `incident` (default) or `postMortem` (only after a postmortem exists).

Download is two steps:

1. `GET /api/v1/incident/{id}/document/{docId}/download-url` → `{ "url": "...", "expiresAt": "..." }`
2. GET that `url` (uploads: signed, 10 minutes, no JWT; links: stored URL, `expiresAt` null)

Mint the URL on click, not on page load.

`DELETE /api/v1/incident/{id}/document/{docId}` → `{ "status": true }`

### Postmortem + RCA

One document per incident. `GET` returns `{ "data": null }` until the first write. `PUT` creates then replaces (full replace).

`PUT /api/v1/incident/{id}/postmortem`

```json
{
  "status": "draft",
  "summary": "Payment pool saturation caused checkout 5xx for 22 minutes.",
  "impact": "Checkout unavailable; ~12k failed payments.",
  "detection": "Customer reports, then the 5xx alert.",
  "resolution": "Restarted the payment workers and raised the pool ceiling.",
  "rootCause": {
    "method": "fiveWhys",
    "whys": [
      "The pool saturated",
      "Connections were never returned",
      "A retry loop held them open"
    ],
    "contributingFactors": ["No alert on pool utilisation"],
    "statement": "A retry loop in the payment client exhausted the connection pool."
  },
  "whatWentWell": ["Secondary region took traffic quickly"],
  "whatWentWrong": ["No pool-utilisation alert"],
  "lessonsLearned": ["Alert on pool wait time, not only 5xx"],
  "authorId": "66a2c0de5f1a2b3c4d5e6f80",
  "reviewerIds": ["66a2c0de5f1a2b3c4d5e6f81"],
  "dueAt": "2026-08-25T12:00:00Z"
}
```

Only `summary` is required. `rootCause.method`: `fiveWhys` | `fishbone` | `timeline` | `other`. `dueAt` is not enforced.

`POST /api/v1/incident/{id}/postmortem/publish`  
Sets `published`, stamps `publishedAt` once, writes a timeline entry. Idempotent. Sending `"status": "published"` on PUT does the same. No postmortem yet → `404`.

Show incident then has:

```json
{
  "postMortem": {
    "id": "66d1...",
    "status": "published",
    "authorId": "66a2...",
    "dueAt": "2026-08-25T12:00:00Z",
    "publishedAt": "2026-08-21T09:00:00Z"
  }
}
```

Full document: `GET /api/v1/incident/{id}/postmortem`.

### Action items

`POST /api/v1/incident/{id}/action-item` → `201`

```json
{
  "title": "Add a circuit breaker to the payment client",
  "description": "The pool saturated at 200 connections.",
  "ownerId": "66a2c0de5f1a2b3c4d5e6f80",
  "teamId": "66a1c0de5f1a2b3c4d5e6f70",
  "priority": "high",
  "category": "prevention",
  "status": "open",
  "dueAt": "2026-08-27T12:00:00Z",
  "postMortemId": "66d1c0de5f1a2b3c4d5e6f90"
}
```

Defaults: `status: open`, `priority: medium`, `category: other`.  
`category`: `prevention` | `detection` | `mitigation` | `process` | `documentation` | `other`.  
`status`: `open` | `inProgress` | `blocked` | `done` | `cancelled`.  
`postMortemId` must belong to **this** incident. `completedAt` is server-managed (set on first `done`, cleared on reopen).

`PUT` same body (full replace).

Cross-incident backlog (items you own, created, or assigned to your teams):

`GET /api/v1/incident-action-item?open=1&overdue=1&search=circuit&incidentId=...`

Each row includes `incident: { id, title, severity, status }`. Scoped by ownership, not incident visibility — clicking through can 403.

---

## 4. Acknowledge

Ack is **per team**, not per incident. First ack moves `open` → `investigating`. Later acks from other teams do not change status.

```
Incident open, teams: [payments, platform]
  │
  ├─ POST /incident/{id}/acknowledge
  │      body empty  → ack every assigned team the caller is in (that has not acked)
  │      { teamId }  → ack that one team
  │
  ├─ first ack  → status investigating
  ├─ same team again → 403
  └─ already resolved → 403
```

`POST /api/v1/incident/{id}/acknowledge`

All of the caller's remaining teams:

```json
{}
```

One team (use this when the user is on more than one assigned team):

```json
{ "teamId": "66a1c0de5f1a2b3c4d5e6f70" }
```

Response is the full incident. After ack:

```json
{
  "status": "investigating",
  "acknowledgements": [
    {
      "teamId": "66a1c0de5f1a2b3c4d5e6f70",
      "acknowledgedBy": "66a2c0de5f1a2b3c4d5e6f80",
      "acknowledgedAt": "2026-08-20T04:20:11Z"
    }
  ],
  "teams": [
    {
      "id": "66a1c0de5f1a2b3c4d5e6f70",
      "name": "payments",
      "onCallPlan": { "id": "66b1...", "name": "payments-primary" },
      "acknowledgement": {
        "acknowledgedBy": "66a2c0de5f1a2b3c4d5e6f80",
        "acknowledgedAt": "2026-08-20T04:20:11Z"
      }
    },
    {
      "id": "66a1c0de5f1a2b3c4d5e6f80",
      "name": "platform",
      "onCallPlan": null,
      "acknowledgement": null
    }
  ],
  "canAcknowledge": false
}
```

`canAcknowledge` is false when the user cannot ack **or** when every team they belong to has already acked. Use `teams[].acknowledgement` to tell those apart (`null` = pending).

A system timeline entry is written on ack (`type: acknowledged`, `meta.teamIds`) plus `statusChanged` when this was the first ack.

UI:

- one assigned team the user is in → send no body
- several → team picker, send `teamId`
- hide/disable the button when `canAcknowledge` is false
