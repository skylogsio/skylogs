<?php

namespace App\Http\Middleware;

use App\Enums\ClusterType;
use App\Services\ClusterService;
use App\Services\UserService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClusterAgentValidateMiddleware
{
    public function __construct(protected ClusterService $clusterService, protected UserService $userService) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->clusterService->type() === ClusterType::MAIN) {
            return $next($request);
        }

        $this->authenticateOriginalUser($request);

        return $next($request);
    }

    protected function authenticateOriginalUser(Request $request): void
    {
        $originalAuth = $request->header('X-Original-Authorization');

        if (empty($originalAuth)) {
            return;
        }

        $request->headers->set('Authorization', $originalAuth);

    }
}
