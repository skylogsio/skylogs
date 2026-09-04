<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the endpoints the Raft sidecar and the other HA nodes call.
 *
 * The caller is a sidecar container rather than a user, so JWT and Sanctum are
 * the wrong shape; a shared secret is deterministic and unit testable, and
 * matches the house style of ClusterAuth and WebhookAuth. The CIDR list is
 * defence in depth rather than the primary control: in Docker and Kubernetes
 * the sidecar's observed source address changes with the network driver and
 * with any proxy in front, so it cannot be relied on alone.
 */
class HaNodeAuth
{
    public const SECRET_HEADER = 'X-Skylogs-HA-Secret';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ha.enabled')) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'High availability is disabled on this node');
        }

        $secret = (string) config('ha.node_secret');

        if ($secret === '' || ! hash_equals($secret, (string) $request->header(self::SECRET_HEADER, ''))) {
            abort(Response::HTTP_UNAUTHORIZED, 'Wrong HA node secret');
        }

        $allowedCidrs = array_values((array) config('ha.allowed_cidrs'));

        if ($allowedCidrs !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowedCidrs)) {
            abort(Response::HTTP_FORBIDDEN, 'HA node address is not allowed');
        }

        return $next($request);
    }
}
