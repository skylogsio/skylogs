# Skylogs Application Conventions

This section documents infrastructure and domain patterns for the Skylogs backend (`apps/backend`). **New code should follow standard Laravel practices** (Form Requests, API Resources, RESTful controller method names, policies). Legacy controllers may still use older patterns — do not copy those when writing new endpoints.

This backend has no Vite frontend and no `package.json`. Ignore npm, Vite, and frontend-bundling instructions. It is not deployed on Laravel Cloud (`boost.json` has `"cloud": false`).

## Domain

Skylogs is a monitoring and alerting platform. It integrates with Prometheus, Zabbix, Sentry, Grafana, Elastic, Splunk, PMM, and similar data sources. Core concepts include alert rules, endpoints, teams, data sources, incidents, on-call plans, runbooks, notification channels (SMS, email, Telegram, call), and notification deliveries.

High availability is separate from the cluster agent. Each HA node has its own backend and MongoDB. A Raft sidecar elects the leader. Three channels move data between nodes: alert-state replication, config sync, and history sync. Cluster agent sync (`GET /api/cluster/sync-data`) is not HA.

## MongoDB

- Domain data uses the `mongodb` connection (`DB_CONNECTION=mongodb` in `.env.example`). Use the Boost `database-query-mongodb` tool for read-only queries. `database-query` and `database-schema` are SQL-shaped and do not describe these documents.
- The Laravel rule about repeating every column attribute when modifying a column applies to SQL migrations. MongoDB documents do not use that rule.
- All domain models extend `App\Models\BaseModel` (or `User`, which extends MongoDB's authenticatable base).
- Primary key is `_id` (MongoDB ObjectId). Route constraints use `[0-9a-fA-F]{24}`.
- Timestamps are camelCase: `createdAt`, `updatedAt`, `deletedAt`.
- Use `MongoDB\Laravel\Schema\Blueprint` in migrations, not the SQL blueprint.
- Foreign keys use camelCase field names (e.g. `ownerId`, `userIds`, `userId`). Both `$model->id` and `$model->_id` may appear in existing code — match the surrounding file.
- Unique validation rules must specify the MongoDB key column: `Rule::unique('teams')->ignore($id, '_id')`.

## API Layer

- The client API is versioned under `/api/v1` in `routes/api.php`.
- Client controllers are in `App\Http\Controllers\V1\` (including incidents, and nested folders for alert rules, config, profile, and webhooks).
- HA node controllers live in `App\Http\Controllers\Ha\`. The cluster agent controller lives in `App\Http\Controllers\Cluster\`.
- These routes are deliberately outside `/api/v1`:
  - `POST /api/ha/apply`, `GET /api/ha/config-sync`, and `GET /api/ha/history-sync` use `haNodeAuth` and the `X-Skylogs-HA-Secret` header.
  - `GET /api/cluster/sync-data` uses `clusterAuth`.
- **New endpoints:** use RESTful controller method names (`index`, `store`, `show`, `update`, `destroy`), Form Request classes for validation, and Eloquent API Resources for responses.
- Request/response fields use camelCase (e.g. `perPage`, `ownerId`, `userIds`) to match existing MongoDB document fields and the frontend API contract.
- **Legacy note:** existing controllers may use PascalCase actions (`Index`, `Create`, …), inline `Validator::validate()`, and raw `response()->json()`. Refactor toward Laravel standards when touching those files; do not extend legacy patterns in new code.

## Authentication & Authorization

- Product login is JWT via `tymon/jwt-auth`. Login is `POST /api/v1/auth/login`.
- `.env.example` sets `AUTH_GUARD=api`. The `api` guard's driver is `jwt`. If `AUTH_GUARD` is missing, `config/auth.php` falls back to the `web` guard.
- Sanctum is installed (`HasApiTokens`, a `sanctum` guard, and Spatie guards `api` and `sanctum`). It is not the product login. New endpoints stay on the `api` guard.
- Protected client routes use the `auth` middleware. In tests: `$this->actingAs($user, 'api')`.
- Roles use Spatie Permission (`Constants` enum: `owner`, `manager`, `member`). Apply via `role:` middleware on routes.
- Custom middleware aliases (registered in `bootstrap/app.php`): `apiAuth`, `webhookAuth`, `clusterAuth`, `clusterProxy`, `clusterAgentValidate`, `haNodeAuth`, `horizonBasicAuth`.

## Data shape

Write code for the shape the application stores now. Endpoint ids on alert rules (`endpointIds`) are strings. Do not add branches for other stored types, legacy field names, or missing nested formats.

When a change would need that compatibility, ask first. Existing documents can be normalized with a tinker command or a script so the application code stays on one shape.

## Services & Business Logic

- Put reusable business logic in `App\Services\` (e.g. `TeamService`, `AlertRuleService`). Inject services via constructor property promotion.
- Authorization checks and computed response attributes (e.g. `canEdit`, `canDelete`) belong in services, not controllers.
- Cache invalidation for domain data often lives in service `flushCache()` methods and model observers.

## Skills

Activate project skills in `.cursor/skills/` when relevant:

- `laravel-best-practices` — default patterns for new and refactored code.
- `pest-testing` — all test authoring.
- `configuring-horizon` — queue workers and Horizon dashboard.

## Testing

- Feature tests hit HTTP routes (`/api/v1/...`). Unit tests may invoke controllers or services directly.
- Use `Tests\Support\*` helpers and factories where they exist (e.g. `TeamTestData`).
- Set `config(['cache.default' => 'array'])` in tests that touch cached services.
- Clean up created MongoDB documents in `afterEach` when tests write to the database.
