<?php

namespace App\Http\Middleware;

use App\Enums\AlertRuleType;
use App\Services\ApiService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAlertAuth
{
    public function __construct(protected ApiService $apiService) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $bearerToken = $request->bearerToken();

        $alert = null;
        if ($request->routeIs('webhook.notification')) {
            $alert = $this->apiService->alertRuleByToken($bearerToken, AlertRuleType::NOTIFICATION);
        } elseif ($request->routeIs('webhook.api.*')) {
            $alert = $this->apiService->alertRuleByToken($bearerToken, AlertRuleType::API);
        }
        if ($alert) {
            return $next($request->merge(['alert' => $alert, 'apiToken' => $bearerToken]));
        }
        abort(Response::HTTP_UNAUTHORIZED);
    }
}
