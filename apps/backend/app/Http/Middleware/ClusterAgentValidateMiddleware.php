<?php

namespace App\Http\Middleware;

use App\Enums\ClusterType;
use App\Services\ClusterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClusterAgentValidateMiddleware
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


        $mainToken = str_replace('Bearer ', '', $originalAuth);

        if (empty($mainToken)) {
            return;
        }

        try {

            $decoded = JWT::decode(
                $mainToken,
                new Key(config('jwt.main_secret'), 'HS256')
            );

            $mainUserId = $decoded->sub ?? null;

            if (! $mainUserId) {
                return;
            }

            // 2️⃣ Find agent user by main_user_id
            $agentUser = User::where('main_user_id', $mainUserId)->first();

            if (! $agentUser) {
                return;
            }

            // 3️⃣ Generate AGENT token
            $agentToken = auth('api')->login($agentUser);

            // 4️⃣ Replace Authorization header
            $request->headers->set('Authorization', 'Bearer ' . $agentToken);

            // 5️⃣ Authenticate agent user
            auth('api')->setToken($agentToken)->authenticate();


        } catch (\Exception $e) {
            // Non-fatal: cluster token already granted access.
            // Just leave the guard unauthenticated.
        }
    }
}
