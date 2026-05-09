<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaClient
{
    public function dashboardStatus(): array
    {
        $enabled = (bool) config('readirect.ollama.enabled')
            && (bool) config('readirect.agent_feedback.miss_ciel_ollama_enabled');
        $baseUrl = rtrim((string) config('readirect.ollama.base_url', 'http://127.0.0.1:11434'), '/');
        $model = (string) config('readirect.ollama.model', 'qwen3:4b');

        if (! $enabled) {
            return [
                'enabled' => false,
                'connected' => false,
                'status' => 'disabled',
                'label' => 'LLM disabled',
                'message' => 'Miss Ciel is using scripted feedback. Local Ollama coaching is disabled.',
                'base_url' => $baseUrl,
                'model' => $model,
                'provider' => 'Ollama',
            ];
        }

        try {
            $response = Http::timeout((int) config('readirect.ollama.timeout_seconds', 10))
                ->acceptJson()
                ->get($baseUrl.'/api/tags');
        } catch (ConnectionException $exception) {
            $this->logFailure('connection_failed', $exception->getMessage());

            return $this->dashboardFailure('unavailable', 'Laravel cannot reach the local Ollama service.', $baseUrl, $model);
        }

        if (! $response->successful()) {
            $this->logFailure('http_'.$response->status(), $response->body());

            return $this->dashboardFailure('unavailable', 'Ollama responded, but the model list could not be checked.', $baseUrl, $model);
        }

        $payload = $response->json();
        $models = collect(is_array($payload) ? ($payload['models'] ?? []) : [])
            ->map(fn ($item) => is_array($item) ? (string) ($item['name'] ?? '') : '')
            ->filter()
            ->values();
        $modelInstalled = $models->contains($model);

        return [
            'enabled' => true,
            'connected' => $modelInstalled,
            'status' => $modelInstalled ? 'connected' : 'model_missing',
            'label' => $modelInstalled ? 'LLM Connected' : 'LLM Model Missing',
            'message' => $modelInstalled
                ? 'Miss Ciel can use local Ollama coaching with scripted fallback safety.'
                : 'Ollama is running, but the configured Miss Ciel model is not installed.',
            'base_url' => $baseUrl,
            'model' => $model,
            'provider' => 'Ollama',
            'installed_models' => $models->all(),
        ];
    }

    public function generate(string $prompt): array
    {
        if (! (bool) config('readirect.ollama.enabled')) {
            return ['text' => null, 'error' => 'disabled', 'latency_ms' => null];
        }

        $baseUrl = rtrim((string) config('readirect.ollama.base_url', 'http://127.0.0.1:11434'), '/');
        $started = microtime(true);

        try {
            $response = Http::timeout((int) config('readirect.ollama.timeout_seconds', 10))
                ->post($baseUrl.'/api/generate', [
                    'model' => config('readirect.ollama.model', 'qwen3:4b'),
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.4,
                        'num_predict' => 60,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $this->logFailure('connection_failed', $exception->getMessage());

            return ['text' => null, 'error' => 'connection_failed', 'latency_ms' => $this->latency($started)];
        }

        if (! $response->successful()) {
            $this->logFailure('http_'.$response->status(), $response->body());

            return ['text' => null, 'error' => 'http_'.$response->status(), 'latency_ms' => $this->latency($started)];
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $this->logFailure('invalid_json', null);

            return ['text' => null, 'error' => 'invalid_json', 'latency_ms' => $this->latency($started)];
        }

        $text = trim((string) ($payload['response'] ?? ''));
        if ($text === '') {
            $this->logFailure('empty_response', null);

            return ['text' => null, 'error' => 'empty_response', 'latency_ms' => $this->latency($started)];
        }

        return ['text' => $text, 'error' => null, 'latency_ms' => $this->latency($started)];
    }

    private function latency(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    private function logFailure(string $reason, ?string $detail): void
    {
        $context = ['reason' => $reason];

        if ((bool) config('readirect.ollama.debug') && $detail) {
            $context['detail'] = str($detail)->limit(500)->toString();
        }

        Log::warning('Miss Ciel Ollama feedback fell back to scripted feedback.', $context);
    }

    private function dashboardFailure(string $status, string $message, string $baseUrl, string $model): array
    {
        return [
            'enabled' => true,
            'connected' => false,
            'status' => $status,
            'label' => 'LLM Disconnected',
            'message' => $message,
            'base_url' => $baseUrl,
            'model' => $model,
            'provider' => 'Ollama',
            'installed_models' => [],
        ];
    }
}
