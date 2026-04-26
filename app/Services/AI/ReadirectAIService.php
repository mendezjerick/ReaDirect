<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReadirectAIService
{
    public function health(): array
    {
        return $this->get('health');
    }

    public function version(): array
    {
        return $this->get('version');
    }

    public function analyzeText(array $payload): array
    {
        return $this->post('analyze_text', $payload);
    }

    public function analyzeAudio(array $payload): array
    {
        return $this->post('analyze_audio', $payload);
    }

    public function recommendNext(array $payload): array
    {
        return $this->post('recommend_next', $payload);
    }

    public function contentItem(array $payload): array
    {
        return $this->post('content_item', $payload);
    }

    public function isAvailable(): bool
    {
        $response = $this->health();

        return (bool) ($response['ok'] ?? false);
    }

    private function get(string $endpointKey): array
    {
        if (! config('readirect_ai.enabled')) {
            return $this->disabledResponse();
        }

        try {
            $response = $this->client()->get($this->url($endpointKey));

            return $this->normalizeResponse($response->json(), $response->successful(), $response->status());
        } catch (Throwable $exception) {
            return $this->failureResponse($endpointKey, $exception);
        }
    }

    private function post(string $endpointKey, array $payload): array
    {
        if (! config('readirect_ai.enabled')) {
            return $this->disabledResponse();
        }

        try {
            $response = $this->client()->post($this->url($endpointKey), $this->sanitizePayload($payload));

            return $this->normalizeResponse($response->json(), $response->successful(), $response->status());
        } catch (Throwable $exception) {
            return $this->failureResponse($endpointKey, $exception);
        }
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::timeout((int) config('readirect_ai.timeout_seconds', 60))
            ->acceptJson()
            ->asJson();

        $token = trim((string) config('readirect_ai.api_token'));

        if ($token !== '') {
            $client = $client->withHeaders(['X-ReaDirect-AI-Token' => $token]);
        }

        return $client;
    }

    private function url(string $endpointKey): string
    {
        $baseUrl = rtrim((string) config('readirect_ai.base_url'), '/');
        $endpoint = (string) Arr::get(config('readirect_ai.endpoints'), $endpointKey, '/');

        return $baseUrl.'/'.ltrim($endpoint, '/');
    }

    private function normalizeResponse(mixed $body, bool $successful, int $status): array
    {
        $data = is_array($body) ? $body : [];
        $data['ok'] = $successful && (bool) ($data['ok'] ?? true);
        $data['http_status'] = $status;

        if (! $successful) {
            $data['error'] = $data['error'] ?? 'readirect_ai_http_error';
        }

        return $data;
    }

    private function disabledResponse(): array
    {
        return [
            'ok' => false,
            'error' => 'readirect_ai_disabled',
            'warnings' => ['ReaDirect AI service is disabled.'],
        ];
    }

    private function failureResponse(string $endpointKey, Throwable $exception): array
    {
        $safeMessage = match (true) {
            $exception instanceof ConnectionException => 'Could not connect to ReaDirect AI service.',
            $exception instanceof RequestException => 'ReaDirect AI service request failed.',
            default => 'ReaDirect AI service is unavailable.',
        };

        Log::warning('ReaDirect AI service request failed.', [
            'endpoint' => $endpointKey,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        return [
            'ok' => false,
            'error' => 'readirect_ai_unavailable',
            'warnings' => [$safeMessage],
        ];
    }

    private function sanitizePayload(array $payload): array
    {
        unset($payload['api_token'], $payload['token']);

        return $payload;
    }
}
