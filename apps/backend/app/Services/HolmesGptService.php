<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HolmesGptService
{
    /**
     * @param  list<array{role: string, content: string}>  $conversationHistory
     * @return array{analysis: string, conversationHistory: list<array{role: string, content: string}>}
     */
    public function chat(string $ask, array $conversationHistory = []): array
    {
        $baseUrl = config('holmes.base_url');

        if ($baseUrl === '') {
            throw new HttpException(503, 'HolmesGPT is not configured.');
        }

        $payload = [
            'ask' => $ask,
            'conversation_history' => $conversationHistory,
        ];

        $model = config('holmes.model');
        if (! empty($model)) {
            $payload['model'] = $model;
        }

        $request = Http::timeout(config('holmes.timeout'))
            ->acceptJson()
            ->asJson();

        $apiKey = config('holmes.api_key');
        if (! empty($apiKey)) {
            $request = $request->withHeaders(['X-API-Key' => $apiKey]);
        }

        try {
            $response = $request->post("{$baseUrl}/api/chat", $payload);
            $response->throw();
        } catch (RequestException $exception) {
            $statusCode = $exception->response?->status() ?? 502;

            throw new HttpException(
                $statusCode >= 500 ? 502 : $statusCode,
                'HolmesGPT request failed.',
                $exception
            );
        }

        $data = $response->json();

        return [
            'analysis' => (string) ($data['analysis'] ?? ''),
            'conversationHistory' => $data['conversation_history'] ?? $conversationHistory,
        ];
    }
}
