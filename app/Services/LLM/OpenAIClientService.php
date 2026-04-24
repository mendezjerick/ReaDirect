<?php

namespace App\Services\LLM;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIClientService
{
    public function generateText(string $systemPrompt, string $userPrompt, array $options = []): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $apiKey = (string) config('readirect.openai.api_key');

        if (trim($apiKey) === '') {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) ($options['timeout_seconds'] ?? config('readirect.openai.timeout_seconds', 30)))
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $options['model'] ?? config('readirect.openai.model', 'gpt-4.1-mini'),
                    'instructions' => $systemPrompt,
                    'input' => $userPrompt,
                    'temperature' => $options['temperature'] ?? config('readirect.openai.temperature', 0.4),
                    'max_output_tokens' => $options['max_output_tokens'] ?? config('readirect.openai.max_output_tokens', 120),
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('OpenAI request failed safely.', [
                'reason' => 'connection_exception',
                'message' => $this->safeError($exception->getMessage()),
            ]);

            return null;
        } catch (\Throwable $exception) {
            Log::warning('OpenAI request failed safely.', [
                'reason' => 'unexpected_exception',
                'message' => $this->safeError($exception->getMessage()),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('OpenAI request returned a non-success response.', [
                'status' => $response->status(),
                'message' => $this->safeError($response->json('error.message')),
            ]);

            return null;
        }

        return $this->extractText($response->json());
    }

    public function isEnabled(): bool
    {
        return (bool) config('readirect.openai.enabled', false);
    }

    private function extractText(array $payload): ?string
    {
        if (isset($payload['output_text']) && trim((string) $payload['output_text']) !== '') {
            return trim((string) $payload['output_text']);
        }

        foreach ($payload['output'] ?? [] as $outputItem) {
            foreach ($outputItem['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && trim((string) ($content['text'] ?? '')) !== '') {
                    return trim((string) $content['text']);
                }
            }
        }

        return null;
    }

    private function safeError(?string $message): ?string
    {
        if (! $message) {
            return null;
        }

        $apiKey = (string) config('readirect.openai.api_key');
        $safe = $apiKey !== '' ? str_replace($apiKey, '[redacted]', $message) : $message;

        return str($safe)->limit(300)->toString();
    }
}
