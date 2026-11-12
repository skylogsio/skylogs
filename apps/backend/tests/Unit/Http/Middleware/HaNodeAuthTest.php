<?php

use App\Http\Middleware\HaNodeAuth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Runs the middleware and reports the status the caller would have seen.
 */
function haNodeAuthStatus(?string $secret, string $ip = '10.0.0.5'): int
{
    $request = Request::create('/api/ha/apply', 'POST', server: ['REMOTE_ADDR' => $ip]);

    if ($secret !== null) {
        $request->headers->set(HaNodeAuth::SECRET_HEADER, $secret);
    }

    try {
        (new HaNodeAuth)->handle($request, fn (): Response => new Response('', Response::HTTP_OK));
    } catch (HttpException $exception) {
        return $exception->getStatusCode();
    }

    return Response::HTTP_OK;
}

beforeEach(function () {
    config([
        'ha.enabled' => true,
        'ha.node_secret' => 'the-shared-secret',
        'ha.allowed_cidrs' => [],
    ]);
});

describe('HaNodeAuth', function () {
    it('lets the sidecar through with the configured secret', function () {
        expect(haNodeAuthStatus('the-shared-secret'))->toBe(Response::HTTP_OK);
    });

    it('rejects a wrong secret', function () {
        expect(haNodeAuthStatus('not-the-secret'))->toBe(Response::HTTP_UNAUTHORIZED);
    });

    it('rejects a missing secret header', function () {
        expect(haNodeAuthStatus(null))->toBe(Response::HTTP_UNAUTHORIZED);
    });

    it('rejects every caller while no secret is configured, rather than letting everyone in', function () {
        config(['ha.node_secret' => '']);

        expect(haNodeAuthStatus(''))->toBe(Response::HTTP_UNAUTHORIZED);
    });

    it('refuses to serve the endpoint at all when ha is disabled', function () {
        config(['ha.enabled' => false]);

        expect(haNodeAuthStatus('the-shared-secret'))->toBe(Response::HTTP_SERVICE_UNAVAILABLE);
    });

    it('accepts an address inside the allowed range', function () {
        config(['ha.allowed_cidrs' => ['10.0.0.0/24']]);

        expect(haNodeAuthStatus('the-shared-secret', '10.0.0.5'))->toBe(Response::HTTP_OK);
    });

    it('rejects an address outside the allowed range even with the right secret', function () {
        config(['ha.allowed_cidrs' => ['10.0.0.0/24']]);

        expect(haNodeAuthStatus('the-shared-secret', '192.168.1.5'))->toBe(Response::HTTP_FORBIDDEN);
    });

    it('accepts any address while the range list is empty', function () {
        expect(haNodeAuthStatus('the-shared-secret', '203.0.113.9'))->toBe(Response::HTTP_OK);
    });
});
