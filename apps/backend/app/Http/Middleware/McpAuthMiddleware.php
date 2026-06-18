<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class McpAuthMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('mcp.api_token');

        if (empty($token)) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'MCP API token is not configured.');
        }

        if ($request->bearerToken() !== $token) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid MCP API token.');
        }

        return $next($request);
    }
}
