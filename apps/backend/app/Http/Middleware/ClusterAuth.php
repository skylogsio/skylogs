<?php

namespace App\Http\Middleware;

use App\Enums\ClusterType;
use App\Services\ClusterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClusterAuth
{
    public function __construct(protected ClusterService $clusterService) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->clusterService->type() == ClusterType::MAIN) {
            $bearerToken = $request->bearerToken();
            $cluster = app(ClusterService::class)->clusterByToken($bearerToken);
            if ($cluster) {
                return $next($request);
            }
            abort(Response::HTTP_UNAUTHORIZED, 'Wrong cluster token');
        }
        abort(Response::HTTP_VERSION_NOT_SUPPORTED);

    }
}
