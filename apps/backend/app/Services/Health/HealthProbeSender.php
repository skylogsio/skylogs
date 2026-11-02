<?php

namespace App\Services\Health;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class HealthProbeSender
{
    public function send(HealthProbe $probe): Response
    {
        return $this->pending(Http::createPendingRequest(), $probe)
            ->send($probe->method, $probe->url, $this->options($probe));
    }

    public function pending(PendingRequest $pending, HealthProbe $probe): PendingRequest
    {
        $pending = $pending
            ->connectTimeout(HealthProbe::CONNECT_TIMEOUT_SECONDS)
            ->timeout($probe->effectiveTimeout())
            ->withOptions(['verify' => $probe->verifyTls])
            ->withHeaders($probe->headers);

        if (is_string($probe->bearerToken) && $probe->bearerToken !== '') {
            $pending = $pending->withToken($probe->bearerToken);
        }

        if (is_string($probe->username) && $probe->username !== '') {
            $pending = $pending->withBasicAuth($probe->username, (string) $probe->password);
        }

        if ($probe->json !== null) {
            $pending = $pending->acceptJson();
        }

        return $pending;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(HealthProbe $probe): array
    {
        if ($probe->json !== null) {
            return ['json' => $probe->json];
        }

        if ($probe->body !== null) {
            return ['body' => $probe->body];
        }

        return [];
    }
}
