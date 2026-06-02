<?php

use Illuminate\Support\Facades\Artisan;

/**
 * Routes intentionally excluded from OpenAPI (internal, welcome, or doc UI).
 *
 * @return list<string>
 */
function openApiExcludedRoutePatterns(): array
{
    return [
        'api',
        'api/cluster/sync-data',
        'api/documentation',
        'api/docs',
        'api/oauth2-callback',
    ];
}

/**
 * @return list<string>
 */
function documentedApiPaths(): array
{
    $docsPath = storage_path('api-docs/api-docs.json');
    $docs = json_decode(file_get_contents($docsPath), true, flags: JSON_THROW_ON_ERROR);

    return array_keys($docs['paths']);
}

/**
 * @return list<string>
 */
function applicationApiRoutes(): array
{
    Artisan::call('route:list', ['--path' => 'api', '--except-vendor' => true, '--json' => true]);
    $routes = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    $excluded = openApiExcludedRoutePatterns();

    return collect($routes)
        ->pluck('uri')
        ->unique()
        ->reject(fn (string $uri) => in_array($uri, $excluded, true))
        ->sort()
        ->values()
        ->all();
}

/**
 * @return list<string>
 */
function normalizeRouteUriToOpenApiPath(string $uri): string
{
    return '/'.ltrim($uri, '/');
}

it('documents alert rule create payloads per type with discriminator', function () {
    $docsPath = storage_path('api-docs/api-docs.json');

    expect($docsPath)->toBeReadableFile();

    $docs = json_decode(file_get_contents($docsPath), true, flags: JSON_THROW_ON_ERROR);

    $create = $docs['paths']['/api/v1/alert-rule']['post']['requestBody']['content']['application/json']['schema'];

    expect($create['discriminator']['propertyName'])->toBe('type')
        ->and($create['discriminator']['mapping'])->toHaveKeys([
            'api',
            'prometheus',
            'elastic',
            'victoria_logs',
            'zabbix',
        ]);

    expect($docs['components']['schemas']['AlertRuleState']['enum'])->toBe([
        'unknown',
        'warning',
        'critical',
        'triggered',
        'resolved',
    ]);
});

it('documents every application api route except known exclusions', function () {
    $documentedPaths = documentedApiPaths();

    $missing = collect(applicationApiRoutes())
        ->map(fn (string $uri) => normalizeRouteUriToOpenApiPath($uri))
        ->reject(fn (string $path) => in_array($path, $documentedPaths, true))
        ->values()
        ->all();

    expect($missing)->toBeEmpty('Undocumented routes: '.implode(', ', $missing));
});
