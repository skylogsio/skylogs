<?php

namespace App\Services\Health\Targets;

use App\Models\AlertRule;
use App\Services\Health\Contracts\HealthTarget;
use App\Services\Health\HealthProbe;
use App\Services\Health\HealthTargetUnavailable;
use Illuminate\Http\Client\Response;
use Illuminate\Validation\Rule;

final class HttpTarget implements HealthTarget
{
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'regex:/^https?:\/\//i'],
            'method' => ['required', 'string', Rule::in(['GET', 'HEAD', 'POST', 'get', 'head', 'post'])],
            'headers' => ['sometimes', 'array'],
            'headers.*.key' => ['required', 'string'],
            'headers.*.value' => ['required', 'string'],
            'body' => ['nullable', 'string'],
            'expectedStatuses' => ['sometimes', 'array'],
            'expectedStatuses.*' => ['integer', 'between:100,599'],
            'bodyContains' => ['nullable', 'string'],
            'timeoutSeconds' => ['sometimes', 'integer', 'between:1,30'],
            'verifyTls' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(array $validated): array
    {
        $headers = [];

        foreach ($validated['headers'] ?? [] as $header) {
            if (! is_array($header)) {
                continue;
            }

            $headers[] = [
                'key' => (string) $header['key'],
                'value' => (string) $header['value'],
            ];
        }

        $expected = array_map(intval(...), $validated['expectedStatuses'] ?? []);

        return [
            'url' => $validated['url'],
            'method' => strtoupper((string) $validated['method']),
            'headers' => $headers,
            'body' => $validated['body'] ?? null,
            'expectedStatuses' => $expected,
            'bodyContains' => $validated['bodyContains'] ?? null,
            'timeoutSeconds' => (int) ($validated['timeoutSeconds'] ?? 5),
            'verifyTls' => array_key_exists('verifyTls', $validated)
                ? filter_var($validated['verifyTls'], FILTER_VALIDATE_BOOLEAN)
                : true,
        ];
    }

    public function probe(AlertRule $rule): HealthProbe
    {
        $target = is_array($rule->target) ? $rule->target : [];
        $url = $target['url'] ?? null;

        if (! is_string($url) || $url === '' || preg_match('/^https?:\/\//i', $url) !== 1) {
            throw new HealthTargetUnavailable('target missing');
        }

        $headers = [];

        foreach ($target['headers'] ?? [] as $header) {
            if (! is_array($header) || ! isset($header['key'])) {
                continue;
            }

            $headers[(string) $header['key']] = (string) ($header['value'] ?? '');
        }

        $expected = array_map(intval(...), is_array($target['expectedStatuses'] ?? null) ? $target['expectedStatuses'] : []);
        $bodyContains = is_string($target['bodyContains'] ?? null) ? $target['bodyContains'] : null;
        $body = is_string($target['body'] ?? null) ? $target['body'] : null;

        return new HealthProbe(
            method: strtoupper((string) ($target['method'] ?? 'GET')),
            url: $url,
            headers: $headers,
            body: $body,
            timeoutSeconds: (int) ($target['timeoutSeconds'] ?? 5),
            verifyTls: filter_var($target['verifyTls'] ?? true, FILTER_VALIDATE_BOOLEAN),
            evaluate: function (Response $response) use ($expected, $bodyContains): ?string {
                $status = $response->status();
                $allowed = $expected !== []
                    ? in_array($status, $expected, true)
                    : $response->successful();

                if (! $allowed) {
                    return 'HTTP '.$status;
                }

                if ($bodyContains !== null && $bodyContains !== '' && ! str_contains($response->body(), $bodyContains)) {
                    return 'response body mismatch';
                }

                return null;
            },
        );
    }
}
