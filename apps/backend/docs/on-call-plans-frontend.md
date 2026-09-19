# On-Call Plans — Frontend API Guide

This document describes how on-call works in Skylogs, which APIs to call, and the expected create / update flow.

All routes are under `/api/v1`. Send a JWT:

```http
Authorization: Bearer <token>
Accept: application/json
```

`teamId` is a 24-character MongoDB ObjectId.

---

## Mental model

There are **two separate things**. Do not mix them.

| Concept | Owned by | What it stores |
|---|---|---|
| **On-call plan** | One per **team** | Weekly roster: who is on call, when, and how long to wait before the next layer |
| **On-call endpoint** | One per **user** | Which of that user's endpoints (SMS, Telegram, Bale, …) should be paged when they are on call |

The plan never stores phone numbers or chat IDs. It only stores users and time windows. When an incident fires, the backend:

1. Looks up the team's plan.
2. Resolves **who** is on call at that moment (per layer).
3. Looks up that user's endpoint where `onCall: true`.
4. Pages that endpoint.

If a roster user has no on-call endpoint, they still appear on the schedule, but they cannot be notified. The plan is then marked `isComplete: false`.

Incident policies do **not** take an `onCallPlanId`. Attach teams to the policy (`teamIds`). Paging uses each team's plan automatically.

---

## Recommended UI flow

```
1. Each person on the team marks one of their endpoints as on-call
   POST /api/v1/endpoint        (or PUT /api/v1/endpoint/{id})
   body: { ..., onCall: true }

2. Team owner (or admin) downloads the calendar template, fills names, then creates the plan
   GET  /api/v1/on-call-plan/template
   POST /api/v1/team/{teamId}/on-call-plan
   multipart: name + timezone + file (xlsx) + optional layerDelays

3. Anyone on the team can view the plan and who is on call now
   GET  /api/v1/team/{teamId}/on-call-plan
   GET  /api/v1/team/{teamId}/on-call-plan/at
   GET  /api/v1/on-call-plan/current

4. To change the roster, replace the whole plan (same multipart as create)
   PUT  /api/v1/team/{teamId}/on-call-plan
   (use POST + _method=PUT when uploading a file — see below)

5. Optional: delete the plan
   DELETE /api/v1/team/{teamId}/on-call-plan
```

Suggested screens:

1. **Per-user settings** — “Use this endpoint when I am on call” toggle on the endpoint form.
2. **Team on-call** — download the calendar template, fill names, upload with name / timezone / layer delays; show `isComplete` and the `roster` list (who is missing an endpoint).
3. **Who is on call** — call `/at` or `/current` and render each layer’s `onCall` user + endpoint.

A team can have **zero or one** plan. Creating a second one returns 422.

---

## Permissions

| Action | Who |
|---|---|
| View plan, `/at`, `/current`, download template | Team member (owner or in `userIds`) or admin. Template: any logged-in user. |
| Create / update / delete plan | **Team owner** or admin. Regular members get **403**. |
| Set `onCall` on an endpoint | The endpoint owner (same as create/update endpoint) |

`canEdit` and `canDelete` on the plan response match the owner/admin check. Use them to hide the edit UI.

---

## Excel roster format

Create and update **always** send an `.xlsx` file. There is no JSON roster body.

Download a blank styled calendar:

```http
GET /api/v1/on-call-plan/template
Authorization: Bearer <token>
```

Saves as `on-call-plan-template.xlsx`. A ready-to-upload filled example (Layer 1 + Layer 2 only) is at [`on-call-plan-sample.xlsx`](./on-call-plan-sample.xlsx).

The workbook is a **weekly wall calendar**, not a Time/User list.

| Sheet | Imported? | Purpose |
|---|---|---|
| **Instructions** | no | How to fill the file |
| **Layer 1** | yes | Primary roster (paged first) |
| **Layer 2** | yes | Backup roster (paged after the layer 1 delay) |
| **Legend** | no | Color key (blank template only) |

Rules:

- **One roster sheet per layer.** Sheet order among imported sheets is the layer order. Duplicate a Layer sheet to add Layer 3+.
- Ignored sheet titles (case-insensitive): `Instructions`, `Legend`, `People`, `Readme`, `How to`, `Cover`, `Notes`.
- Header row: **Time** in the first column, then **Monday … Sunday** (or `Mon` … `Sun`). Extra columns are ignored.
- Each **cell** is who covers that shift on that day. Type a team member **display name** or **username** (case-insensitive).
- Empty cell = uncovered. Empty rows are skipped.
- Max file size: **2 MB**. Type: `xlsx` only.

### Time column

Each row is one shift. Column A may be:

```
00:00–08:00
```

or only a start:

```
08:00
```

- Dash can be `-`, `–` (en dash), or `—` (em dash).
- Times are `H:MM` or `HH:MM`. `24:00` is allowed as an end time (end of day).
- If the cell is only a start, the shift runs until the **next row’s start** (last row runs until `24:00`).
- You can insert extra rows (`00:00`, `01:00`, … `23:00`) for hourly grids. Consecutive cells with the same name collapse into one window.
- Merged cells are expanded (same person across several hours).
- Days of week stored as ISO numbers: **1 = Monday … 7 = Sunday**.
- Matching is **half-open**: `[start, end)`. `08:00–16:00` covers 08:00 inclusive, 16:00 exclusive.

**Overnight is not supported.** `22:00–06:00` is rejected. Split into `22:00–24:00` and a next-day `00:00–06:00`.

**Windows in the same layer must not overlap** (two people cannot cover the same minutes on the same day in one layer). The calendar grid prevents that unless you use overlapping time ranges in column A. Different layers can overlap; that is how backup / escalation works.

### Name cell

Exact **display name** or **username** of a team member (case-insensitive).

- Must already be on the team (owner or `userIds`).
- If two members share the same name, the cell fails as ambiguous — use username instead.
- Cell fill color is visual only. The importer reads the text.

### Example workbook

**Sheet `Layer 1`** (primary) — 8-hour shifts, one name per cell:

| Time | Monday | Tuesday | Wednesday | Thursday | Friday | Saturday | Sunday |
|---|---|---|---|---|---|---|---|
| 00:00–08:00 | alice | bob | alice | bob | alice | carol | carol |
| 08:00–16:00 | bob | alice | bob | alice | bob | carol | carol |
| 16:00–24:00 | alice | bob | alice | bob | alice | carol | carol |

**Sheet `Layer 2`** (backup) — the same grid, usually one backup person on weekdays.

[`on-call-plan-sample.xlsx`](./on-call-plan-sample.xlsx) is Layer 1 and Layer 2 only, filled with random existing usernames (`k.habibi@toman.ir`, `a.nasiri@toman.ir`, `mt.basiri@toman.ir`).

Timezone and `layerDelays` are **not** in the file. Send them in the multipart body.

---

## On-call plan endpoints

### Create plan

```http
POST /api/v1/team/{teamId}/on-call-plan
Content-Type: multipart/form-data
```

| Field | Required | Notes |
|---|---|---|
| `name` | yes | string, max 255 |
| `timezone` | yes | IANA timezone, e.g. `Asia/Tehran` |
| `file` | yes | `.xlsx` roster |
| `layerDelays` | no | Array of integers, minutes to wait **after** each layer before paging the next. Per item: `1`–`10080` (7 days). Index matches sheet order. Missing indexes default to **15**. |

Example (`FormData`):

```js
const form = new FormData()
form.append('name', 'payments-oncall')
form.append('timezone', 'Asia/Tehran')
form.append('file', xlsxFile) // File / Blob
form.append('layerDelays[0]', '5')   // layer 1 wait before layer 2
form.append('layerDelays[1]', '15')  // layer 2 wait before layer 3 (if any)
```

**201** — `{ data: OnCallPlan }`

Layer delay meaning when an incident opens:

- Layer 1 is paged **immediately**.
- Layer 2 is paged after layer 1’s delay (5 minutes in the example).
- Layer 3 would be paged after 5 + 15 = 20 minutes.
- Later layers only run if the incident policy rule has `escalation.useLayers: true` (the default).

### Get plan

```http
GET /api/v1/team/{teamId}/on-call-plan
```

**200** — `{ data: OnCallPlan }`  
**404** — team has no plan (or team id not found)

### Replace plan

Same multipart body as create. The roster is fully replaced, not patched.

```http
PUT /api/v1/team/{teamId}/on-call-plan
Content-Type: multipart/form-data
```

**PHP does not parse file uploads on PUT.** From the browser, send **POST** and spoof the method:

```js
form.append('_method', 'PUT')
await fetch(`/api/v1/team/${teamId}/on-call-plan`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  body: form, // do not set Content-Type; the browser sets the multipart boundary
})
```

**200** — `{ data: OnCallPlan }`  
**404** — no plan yet (create first)

### Delete plan

```http
DELETE /api/v1/team/{teamId}/on-call-plan
```

**200**

```json
{ "status": true }
```

### Who is on call for one team

```http
GET /api/v1/team/{teamId}/on-call-plan/at?at=2026-08-31T04:30:00Z
```

| Query | Required | Notes |
|---|---|---|
| `at` | no | ISO datetime. Defaults to now. Converted to the **plan timezone**. |

This response is **not** wrapped in `data`.

```json
{
  "at": "2026-08-31T08:00:00+03:30",
  "timezone": "Asia/Tehran",
  "teamId": "...",
  "plan": { "id": "...", "name": "payments-oncall" },
  "layers": [
    {
      "level": 1,
      "escalateAfterMinutes": 5,
      "onCall": {
        "userId": "...",
        "name": "Bob",
        "window": {
          "daysOfWeek": [1],
          "startTime": "08:00",
          "endTime": "16:00"
        },
        "endpoint": { "id": "...", "name": "bob-sms", "type": "sms" }
      }
    },
    {
      "level": 2,
      "escalateAfterMinutes": 15,
      "onCall": { "userId": "...", "name": "Carol", "window": { "...": "..." }, "endpoint": { "...": "..." } }
    }
  ]
}
```

If nobody covers that instant on a layer, `layers[n].onCall` is `null`. If the user is on call but has no on-call endpoint, `onCall.endpoint` is `null`.

### Who is on call across teams

```http
GET /api/v1/on-call-plan/current?teamIds[]={id}&at=2026-08-31T04:30:00Z
```

| Query | Required | Notes |
|---|---|---|
| `teamIds` | no | Array of team ids. Omit to use every team the user can see. Unknown / invisible ids → 422. |
| `at` | no | Same as `/at`. |

**200**

```json
{
  "data": [
    {
      "teamId": "...",
      "teamName": "Payments",
      "plan": { "id": "...", "name": "payments-oncall" },
      "at": "...",
      "timezone": "Asia/Tehran",
      "layers": [ { "level": 1, "escalateAfterMinutes": 5, "onCall": { "...": "..." } } ]
    },
    {
      "teamId": "...",
      "teamName": "No plan yet",
      "plan": null,
      "at": "...",
      "timezone": null,
      "layers": []
    }
  ]
}
```

---

## `OnCallPlan` response shape

Create / get / update wrap this in `{ data: ... }`.

```json
{
  "id": "...",
  "teamId": "...",
  "team": { "id": "...", "name": "Payments" },
  "name": "payments-oncall",
  "timezone": "Asia/Tehran",
  "layers": [
    {
      "level": 1,
      "escalateAfterMinutes": 5,
      "entries": [
        {
          "userId": "...",
          "windows": [
            { "daysOfWeek": [1], "startTime": "00:00", "endTime": "08:00" }
          ]
        }
      ]
    }
  ],
  "roster": [
    {
      "userId": "...",
      "name": "Alice",
      "endpoint": { "id": "...", "name": "alice-sms", "type": "sms" }
    },
    {
      "userId": "...",
      "name": "Bob",
      "endpoint": null
    }
  ],
  "isComplete": false,
  "canEdit": true,
  "canDelete": true,
  "createdAt": "...",
  "updatedAt": "..."
}
```

`isComplete` is `true` only when:

- Every layer has at least one entry, and
- Every unique user in the roster has an endpoint with `onCall: true`.

Use `roster[].endpoint === null` to show “this person still needs to pick an on-call endpoint”. Completing that is an **endpoint** update, not a plan update. Refresh the plan after they save it.

---

## On-call endpoints (notification destinations)

Existing endpoint APIs:

```http
POST /api/v1/endpoint
PUT  /api/v1/endpoint/{id}
```

Add boolean `onCall` to the JSON body.

- `onCall: true` — this is the endpoint used when the owner is on an on-call plan.
- **Only one endpoint per user can be on-call.** Setting a new one to `true` automatically sets the others to `false`.
- Omit `onCall` to leave the flag unchanged.

The endpoint object includes `onCall` in responses.

Typical copy in the UI: “Use this channel when I am on call.”

---

## Errors to handle in the UI

| Status | When | Shape |
|---|---|---|
| **403** | Member tries to create/edit/delete; outsider tries to view | Forbidden |
| **404** | Unknown `teamId`, or GET/PUT/DELETE when the team has no plan | Not found |
| **422** field validation | Missing `name` / `timezone` / `file`, bad timezone, file not xlsx, `layerDelays.*` out of range | Laravel `{ message, errors: { field: [string] } }` |
| **422** second plan | `POST` when a plan already exists | `errors.teamId[0]`: `"This team already has an on-call plan."` |
| **422** overlapping windows | Two windows in one layer share minutes | `errors["layers.0.entries"]` |
| **422** overnight window | `start >= end` | `"Each window must start before it ends. Overnight wrap is not supported."` |
| **422** Excel parse | Bad time string, unknown user, missing columns, empty sheet | **Different shape** — `errors` is an **array of objects**, not a map |

Excel parse example:

```json
{
  "message": "The on-call roster could not be imported.",
  "errors": [
    {
      "sheet": "Layer 1",
      "row": 2,
      "column": "B",
      "message": "User 'Nobody-Here' was not found on this team."
    }
  ]
}
```

`row` can be `null` (e.g. empty sheet / missing headers). `column` is the Excel column letter when the error is a specific cell (`B`, `C`, …). Show `sheet` + `row` + `column` + `message` in the upload error list.

---

## How this ties into incidents (read-only for this screen)

You do not send the plan when creating an incident policy. The policy lists `teamIds`. When a matching alert opens an incident:

1. The policy’s SEV notify endpoints are paged.
2. Layer 1 of each team’s current on-call is paged at the same time.
3. If `useLayers` is true (default), later layers are paged after each layer’s `escalateAfterMinutes`.
4. If the policy requires a commander, the current layer-1 on-call user is assigned.
5. Incident team objects include `{ id, name, onCallPlan: { id, name } | null }`.

If `isComplete` is false, paging may skip a layer because `endpoint` is null — that is why the roster completeness UI matters.

---

## Quick reference

| Method | Path | Auth | Body |
|---|---|---|---|
| `GET` | `/api/v1/team/{teamId}/on-call-plan` | member / admin | — |
| `POST` | `/api/v1/team/{teamId}/on-call-plan` | owner / admin | multipart `name`, `timezone`, `file`, optional `layerDelays` |
| `PUT` | `/api/v1/team/{teamId}/on-call-plan` | owner / admin | same as POST (spoof via POST + `_method=PUT` for files) |
| `DELETE` | `/api/v1/team/{teamId}/on-call-plan` | owner / admin | — |
| `GET` | `/api/v1/team/{teamId}/on-call-plan/at` | member / admin | query `at` |
| `GET` | `/api/v1/on-call-plan/template` | logged-in user | — (xlsx download) |
| `GET` | `/api/v1/on-call-plan/current` | logged-in user | query `teamIds[]`, `at` |
| `POST` | `/api/v1/endpoint` | endpoint owner | JSON including optional `onCall` |
| `PUT` | `/api/v1/endpoint/{id}` | endpoint owner | JSON including optional `onCall` |
