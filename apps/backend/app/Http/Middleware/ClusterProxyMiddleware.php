<?php

namespace App\Http\Middleware;

use App\Enums\ClusterType;
use App\Models\SkylogsInstance;
use App\Services\ClusterService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ClusterProxyMiddleware
{

    public function __construct(protected ClusterService $clusterService)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->clusterService->type() !== ClusterType::MAIN) {
            return $next($request);
        }


        $clusterId = $request->header('X-Cluster');

        if (empty($clusterId)) {
            return $next($request);
        }

        $instance = $this->clusterService->clusterById($clusterId);

        if (!$instance) {
            return response()->json([
                'message' => 'Cluster not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->proxyRequest($request, $instance);
    }

    protected function proxyRequest(Request $request, SkylogsInstance $instance): Response
    {
        $baseUrl = rtrim($instance->getBaseUrl(), '/');

        // Build the target URL (strip /api prefix if already in base, adjust as needed)
        $path = $request->getPathInfo();
        $queryString = $request->getQueryString();
        $targetUrl = $baseUrl . $path . ($queryString ? '?' . $queryString : '');

        // Pass original Authorization header as X-Original-Authorization
        // so the agent knows which user initiated the request (for logging etc.)
        $originalAuth = $request->header('Authorization', '');

        try {
            $pendingRequest = Http::timeout(15)
                ->withToken($instance->token)
                ->withHeader('X-Original-Authorization', $originalAuth)
                ->withHeader('X-Forwarded-By', 'skylogs-main');

            // Forward request body for non-GET methods
            $method = strtolower($request->method());

            $response = match ($method) {
                'get' => $pendingRequest->get($targetUrl),
                'post' => $pendingRequest->withBody(
                    $request->getContent(),
                    $request->header('Content-Type', 'application/json')
                )->post($targetUrl),
                'put' => $pendingRequest->withBody(
                    $request->getContent(),
                    $request->header('Content-Type', 'application/json')
                )->put($targetUrl),
                'patch' => $pendingRequest->withBody(
                    $request->getContent(),
                    $request->header('Content-Type', 'application/json')
                )->patch($targetUrl),
                'delete' => $pendingRequest->delete($targetUrl),
                default => $pendingRequest->get($targetUrl),
            };

            return response($response->body(), $response->status())
                ->withHeaders([
                    'Content-Type' => $response->header('Content-Type') ?? 'application/json',
                    'X-Proxied-Cluster' => $instance->_id,
                ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reach agent cluster.',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }
}
