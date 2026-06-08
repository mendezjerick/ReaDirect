<?php

namespace App\Services\AI;

use App\Services\TTS\AgentTtsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReadirectAIService
{
    public function __construct(
        private readonly AgentTtsService $tts,
    ) {}

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

    public function reinforcementCorrection(array $payload): array
    {
        return $this->post('reinforcement_correction', $payload);
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
                'agent_decision' => $this->agentDecisionStatus(),
                'tts' => $this->tts->dashboardStatus(),
            ];
        }

        $health = $this->health();
        $connected = (bool) ($health['ok'] ?? false);
        $version = $connected ? $this->version() : [];
        $gopThresholds = data_get($health, 'thresholds.gop')
            ?? data_get($version, 'config.gop')
            ?? [];
        $gopEnabled = (bool) data_get($gopThresholds, 'enabled', false);
        $phonemeAvailable = (bool) ($health['wav2vec2_phoneme_available'] ?? false);
        $gopStatus = $health['gop_status'] ?? (! $gopEnabled ? 'Off' : ($phonemeAvailable ? 'Ready' : 'Failed'));
        $dynamicCorrectionThresholds = data_get($health, 'thresholds.dynamic_expected_correction')
            ?? data_get($version, 'config.dynamic_expected_correction')
            ?? [];

        return [
            'enabled' => true,
            'connected' => $connected,
            'status' => $connected ? 'connected' : 'unavailable',
            'label' => $connected ? 'AI Service Connected' : 'AI Service Disconnected',
            'message' => $connected
                ? 'FastAPI ASR service is online.'
                : 'Laravel cannot reach the FastAPI Wav2Vec2 ASR service. Letter, word, and sentence ASR may be unavailable until the AI service is started.',
            'base_url' => $baseUrl,
            'service' => $health['service'] ?? $version['service'] ?? null,
            'version' => $health['version'] ?? $version['version'] ?? null,
            'service_status' => $health['service_status'] ?? $health['status'] ?? null,
            'asr_architecture' => $health['asr_architecture'] ?? data_get($version, 'config.asr.architecture') ?? null,
            'active_asr_model' => $health['active_asr_model'] ?? data_get($version, 'config.asr.active_model') ?? null,
            'active_asr_model_path' => $health['active_asr_model_path'] ?? data_get($version, 'config.asr.active_asr_model_path') ?? null,
            'asr_provider' => $health['model_family'] ?? $health['asr_provider'] ?? data_get($version, 'config.asr.provider') ?? null,
            'model_size' => $health['wav2vec2_asr_model_name'] ?? $health['active_asr_model_path'] ?? $health['active_asr_model'] ?? data_get($version, 'config.asr.model_size'),
            'model_family' => $health['model_family'] ?? null,
            'model_used' => $health['model_used'] ?? $health['wav2vec2_asr_model_name'] ?? $health['active_asr_model_path'] ?? null,
            'model_version' => $health['model_version'] ?? data_get($version, 'config.asr.model_version') ?? null,
            'base_model' => $health['base_model'] ?? data_get($version, 'config.asr.base_model') ?? null,
            'training_type' => $health['training_type'] ?? data_get($version, 'config.asr.training_type') ?? null,
            'training_mix' => $health['training_mix'] ?? data_get($version, 'config.asr.training_mix') ?? null,
            'wav2vec2_asr_available' => $health['wav2vec2_asr_available'] ?? null,
            'wav2vec2_asr_model_name' => $health['wav2vec2_asr_model_name'] ?? null,
            'wav2vec2_phoneme_available' => $health['wav2vec2_phoneme_available'] ?? null,
            'wav2vec2_phoneme_model_name' => $health['wav2vec2_phoneme_model_name'] ?? null,
            'whisper_available' => $health['whisper_available'] ?? null,
            'whisper_removed' => $health['whisper_removed'] ?? null,
            'supported_prompt_types' => $health['supported_prompt_types'] ?? null,
            'correction_layer_enabled' => $health['correction_layer_enabled'] ?? null,
            'expected_centric_scoring_enabled' => $health['expected_centric_scoring_enabled'] ?? null,
            'phoneme_evidence_enabled' => $health['phoneme_evidence_enabled'] ?? null,
            'gop_enabled' => data_get($gopThresholds, 'enabled'),
            'gop_status' => $gopStatus,
            'gop_model_version' => data_get($gopThresholds, 'model_version') ?? data_get($gopThresholds, 'model_name'),
            'gop_thresholds' => $gopThresholds,
            'dynamic_expected_correction_enabled' => data_get($dynamicCorrectionThresholds, 'enabled'),
            'dynamic_expected_correction_thresholds' => $dynamicCorrectionThresholds,
            'thresholds' => $health['thresholds'] ?? null,
            'local_model_paths_loaded' => $health['local_model_paths_loaded'] ?? null,
            'reinforcement_corrections_enabled' => $health['reinforcement_corrections_enabled'] ?? null,
            'reinforcement_corrections_dir' => $health['reinforcement_corrections_dir'] ?? null,
            'reinforcement_files_loaded' => $health['reinforcement_files_loaded'] ?? [],
            'reinforcement_letter_rules_count' => $health['reinforcement_letter_rules_count'] ?? 0,
            'reinforcement_word_rules_count' => $health['reinforcement_word_rules_count'] ?? 0,
            'reinforcement_load_warnings' => $health['reinforcement_load_warnings'] ?? [],
            'audio_quality_validation_enabled' => $health['audio_quality_validation_enabled'] ?? null,
            'pause_detection_enabled' => $health['pause_detection_enabled'] ?? null,
            'uncertainty_decision_enabled' => $health['uncertainty_decision_enabled'] ?? null,
            'audio_quality_thresholds' => $health['audio_quality_thresholds'] ?? null,
            'laravel_response_contract' => [
                'scoring_transcript' => 'corrected_transcript -> transcript -> raw_transcript',
                'learner_display_transcript' => 'displayed_transcript -> corrected_transcript -> transcript -> raw_transcript',
                'admin_debug_transcripts' => 'raw_transcript, corrected_transcript, displayed_transcript',
                'accepted_behavior' => 'accepted letter and word corrections display the expected CSV answer',
                'rejected_behavior' => 'rejected responses display the recognized transcript',
            ],
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
            'agent_decision' => $this->agentDecisionStatus(),
            'tts' => $this->tts->dashboardStatus(),
        ];
    }

    private function agentDecisionStatus(): array
    {
        return [
            'status' => 'deterministic',
            'label' => 'Deterministic Agent Decisions',
            'message' => 'Miss Ciel uses approved rules and dialogue templates. No LLM service is required.',
            'provider' => 'ReaDirect Laravel policy service',
            'mode' => (string) config('readirect.ciel.decision_mode', 'deterministic'),
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

    private function client(): PendingRequest
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
            $detail = $this->formatErrorDetail($data['detail'] ?? null);
            $data['warnings'] = array_values(array_filter([
                ...($data['warnings'] ?? []),
                $detail !== '' ? "ReaDirect AI returned HTTP {$status}: {$detail}" : "ReaDirect AI returned HTTP {$status}.",
            ]));
        }

        return $data;
    }

    private function formatErrorDetail(mixed $detail): string
    {
        if (is_string($detail)) {
            return $detail;
        }

        if (is_array($detail)) {
            $messages = [];

            foreach ($detail as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $location = implode('.', array_map('strval', $item['loc'] ?? []));
                $message = (string) ($item['msg'] ?? $item['type'] ?? '');
                $messages[] = trim($location.' '.$message);
            }

            return implode('; ', array_filter($messages));
        }

        return '';
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

        foreach (['content_metadata', 'current_context', 'current_scoring_context'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] === []) {
                $payload[$key] = (object) [];
            }
        }

        return $payload;
    }
}
