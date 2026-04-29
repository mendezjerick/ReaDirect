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

    public function dashboardStatus(): array
    {
        $baseUrl = rtrim((string) config('readirect_ai.base_url'), '/');

        if (! config('readirect_ai.enabled')) {
            return [
                'enabled' => false,
                'connected' => false,
                'status' => 'disabled',
                'label' => 'AI service disabled',
                'message' => 'ReaDirect AI is disabled in Laravel. Audio analysis will use configured fallback behavior.',
                'base_url' => $baseUrl,
                'troubleshooting_steps' => [
                    'Set READIRECT_AI_ENABLED=true in the Laravel .env file.',
                    'Start the FastAPI service from ReaDirect-AI-ASR.',
                    'Confirm READIRECT_AI_BASE_URL points to the FastAPI host and port.',
                ],
            ];
        }

        $health = $this->health();
        $connected = (bool) ($health['ok'] ?? false);
        $version = $connected ? $this->version() : [];

        return [
            'enabled' => true,
            'connected' => $connected,
            'status' => $connected ? 'connected' : 'unavailable',
            'label' => $connected ? 'AI service connected and running' : 'AI service not connected',
            'message' => $connected
                ? 'FastAPI is reachable. Laravel will delegate AI/ASR analysis to ReaDirect-AI-ASR.'
                : 'Laravel could not reach the ReaDirect-AI-ASR FastAPI service.',
            'base_url' => $baseUrl,
            'service' => $health['service'] ?? $version['service'] ?? null,
            'version' => $health['version'] ?? $version['version'] ?? null,
            'asr_provider' => $health['asr_provider'] ?? data_get($version, 'config.asr.provider'),
            'model_size' => data_get($version, 'config.asr.model_size'),
            'content_index_loaded' => $health['content_index_loaded'] ?? null,
            'cmudict_loaded' => $health['cmudict_loaded'] ?? null,
            'error' => $health['error'] ?? null,
            'warnings' => $health['warnings'] ?? [],
            'troubleshooting_steps' => [
                'Start FastAPI: run the AI repo service on the configured port, usually 8001.',
                'Check READIRECT_AI_BASE_URL in Laravel and the FastAPI host/port in ReaDirect-AI-ASR.',
                'If token auth is enabled, match READIRECT_AI_API_TOKEN in both repositories.',
                'Verify the AI repo model path and ASR provider settings before production use.',
            ],
        ];
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

        foreach (['content_metadata', 'current_context'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] === []) {
                $payload[$key] = (object) [];
            }
        }

        return $payload;
    }
}
