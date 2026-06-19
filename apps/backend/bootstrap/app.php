<?php

use App\Http\Middleware\ApiAlertAuth;
use App\Http\Middleware\ClusterAgentValidateMiddleware;
use App\Http\Middleware\ClusterAuth;
use App\Http\Middleware\ClusterProxyMiddleware;
use App\Http\Middleware\HolmesChatWebAuth;
use App\Http\Middleware\HorizonBasicAuthMiddleware;
use App\Http\Middleware\McpAuthMiddleware;
use App\Http\Middleware\WebhookAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->throttleWithRedis();

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'apiAuth' => ApiAlertAuth::class,
            'webhookAuth' => WebhookAuth::class,
            'clusterAuth' => ClusterAuth::class,
            'clusterProxy' => ClusterProxyMiddleware::class,
            'clusterAgentValidate' => ClusterAgentValidateMiddleware::class,
            'horizonBasicAuth' => HorizonBasicAuthMiddleware::class,
            'mcpAuth' => McpAuthMiddleware::class,
            'holmesChatWebAuth' => HolmesChatWebAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
