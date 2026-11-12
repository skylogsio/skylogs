<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: <<<'DESC'
Skylogs backend REST API.

**Frontend quick start**
1. Authenticate via `POST /api/v1/auth/login` and send `Authorization: Bearer {accessToken}` on protected routes.
2. Browse by tag: AlertRule, Endpoints, Users, Teams, Data Sources, Status, etc.
3. Alert rule **create** uses one URL with different bodies — pick the schema via the `type` discriminator on `POST /api/v1/alert-rule`.
4. **Behavior rules** (notification / template / silent) use the same pattern on `POST /api/v1/alert-rule-behavior-rule/{alertRuleId}` — see tag **AlertRule Behavior Rules**.
5. Public status page data: `GET /api/v1/status/all` (no auth).
6. Webhook tag documents server-to-server callbacks (API token or URL token), not the SPA JWT.

Regenerate after backend changes: `php artisan l5-swagger:generate`
DESC,
    title: 'Skylogs API'
)]
#[OA\Server(
    url: swaggerHost,
    description: 'Skylogs local server'
)]
#[OA\Components(
    securitySchemes: [
        new OA\SecurityScheme(
            securityScheme: 'bearerAuth',
            type: 'http',
            description: 'Enter JWT token (without “Bearer” prefix)',
            bearerFormat: 'JWT',
            scheme: 'bearer'
        ),
    ]
)]
#[OA\SecurityRequirement(name: 'bearerAuth')]
class GeneralDocs {}
