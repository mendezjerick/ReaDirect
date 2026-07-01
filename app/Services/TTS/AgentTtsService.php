<?php

namespace App\Services\TTS;

use App\Support\AgentIdentity;
use App\Services\VoiceLines\VoiceLineService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AgentTtsService
{
    private const CACHE_DIR = 'tts_cache';

    public function __construct(private readonly VoiceLineService $voiceLines) {}

    public function voiceProfile(string $agent): array
    {
        $canonical = $this->canonicalAgent($agent);

        return [
            'agent' => $canonical,
            'display_name' => match ($canonical) {
                'miss_ciel' => AgentIdentity::displayName(AgentIdentity::MISS_CIEL),
                'miss_estelle' => AgentIdentity::displayName(AgentIdentity::MISS_ESTELLE),
                default => AgentIdentity::displayName(AgentIdentity::MISS_VIVIAN),
            },
            'voice' => $this->voiceForAgent($canonical),
            'speed' => $this->speedForAgent($canonical),
            'speed_range' => config('readirect.tts.speed_ranges.'.$canonical),
        ];
    }

    public function speechPayload(string $agent, string $text, bool $includeDebug = false, array $options = []): array
    {
        $profile = $this->voiceProfile($agent);
        $safeText = $this->sanitizeText($text);
        $fallback = $this->fallbackPayload($profile, $safeText);
        $intent = $this->sanitizeKey($options['intent'] ?? null, 64);
        $lineKey = $this->sanitizeKey($options['line_key'] ?? null, 128);
        $metadata = is_array($options['metadata'] ?? null) ? $options['metadata'] : [];
        $metadataPayload = empty($metadata) ? new \stdClass() : (object) $metadata;

        if ($safeText === '') {
            return $fallback;
        }

        $databaseAudio = $this->voiceLines->resolve($profile['agent'], $safeText, $lineKey, $intent);
        if ($databaseAudio !== null) {
            $payload = [
                'agent' => $profile['agent'],
                'display_name' => $profile['display_name'],
                'text' => $databaseAudio['text'] ?: $safeText,
                'voice_enabled' => true,
                'tts_provider' => 'database',
                'audio_url' => $databaseAudio['audio_url'],
                'fallback' => (bool) ($databaseAudio['fallback'] ?? false),
                'text_fallback_allowed' => true,
                'active_audio_type' => $databaseAudio['active_audio_type'] ?? null,
                'active_stage' => $databaseAudio['active_stage'] ?? null,
                'line_key' => $databaseAudio['line_key'] ?? $lineKey,
                'duration_seconds' => $databaseAudio['duration_seconds'] ?? null,
            ];

            return $this->withDebug(
                $payload,
                $includeDebug,
                null,
                0,
                $profile['voice'],
                true
            );
        }

        if ((bool) config('readirect.voice_database.enabled', false)) {
            return $this->withDebug($fallback, $includeDebug, 'database_voice_line_missing_silent', 0);
        }

        if (! (bool) config('readirect.tts.enabled')) {
            return $fallback;
        }

        $cacheKey = $this->cacheKey($profile, $safeText, $intent, $lineKey);
        $cachePath = self::CACHE_DIR.'/'.$cacheKey.'.wav';
        $started = microtime(true);

        $cacheEnabled = (bool) config('readirect.tts.cache_enabled') && ! (bool) config('readirect.tts.cache_bypass', false);

        if ($cacheEnabled && Storage::disk('local')->exists($cachePath)) {
            return $this->successPayload($profile, $safeText, $cacheKey, $includeDebug, true, $this->latency($started));
        }

        try {
            $response = Http::timeout((int) config('readirect.tts.timeout_seconds', 10))
                ->accept('audio/wav')
                ->asJson()
                ->post(rtrim((string) config('readirect.tts.base_url'), '/').'/synthesize', [
                    'agent' => $profile['agent'],
                    'text' => $safeText,
                    'intent' => $intent,
                    'line_key' => $lineKey,
                    'engine' => (string) config('readirect.tts.engine', 'kokoro'),
                    'expressive' => (bool) config('readirect.tts.expressive.enabled', false),
                    'voice' => null,
                    'speed' => $profile['speed'],
                    'cache' => $cacheEnabled,
                    'context' => 'agent_narration',
                    'humanize' => (bool) config('readirect.tts.humanization.text_humanizer_enabled', true),
                    'delivery_control' => (bool) config('readirect.tts.humanization.delivery_control_enabled', true),
                    'audio_humanizer' => (bool) config('readirect.tts.humanization.audio_humanizer_enabled', true),
                    'pause_control' => (bool) config('readirect.tts.humanization.pause_control_enabled', true),
                    'metadata' => $metadataPayload,
                ]);
        } catch (ConnectionException $exception) {
            $this->logFailure('connection_failed', $exception->getMessage());

            return $this->withDebug($fallback, $includeDebug, 'connection_failed', $this->latency($started));
        }

        if (! $response->successful()) {
            $this->logFailure('http_'.$response->status(), $response->body());

            return $this->withDebug($fallback, $includeDebug, 'http_'.$response->status(), $this->latency($started));
        }

        $audio = $response->body();
        if (strlen($audio) < 8) {
            $this->logFailure('empty_audio', null);

            return $this->withDebug($fallback, $includeDebug, 'empty_audio', $this->latency($started));
        }

        Storage::disk('local')->put($cachePath, $audio);

        return $this->successPayload($profile, $safeText, $cacheKey, $includeDebug, false, $this->latency($started));
    }

    public function dashboardStatus(): array
    {
        $enabled = (bool) config('readirect.tts.enabled');
        $baseUrl = rtrim((string) config('readirect.tts.base_url', 'http://127.0.0.1:8002'), '/');
        $provider = (string) config('readirect.tts.provider', 'kokoro');
        $configuredVoices = config('readirect.tts.voices', []);

        if (! $enabled) {
            return [
                'enabled' => false,
                'connected' => false,
                'status' => 'disabled',
                'label' => 'Kokoro Voice Disabled',
                'message' => 'Natural agent voice is disabled. Learner pages will stay silent when no generated audio is available.',
                'base_url' => $baseUrl,
                'provider' => $provider,
                'voices' => $configuredVoices,
            ];
        }

        try {
            $response = Http::timeout((int) config('readirect.tts.timeout_seconds', 10))
                ->acceptJson()
                ->get($baseUrl.'/health');
        } catch (ConnectionException $exception) {
            $this->logFailure('health_connection_failed', $exception->getMessage());

            return $this->dashboardFailure('unavailable', 'Laravel cannot reach the local Kokoro TTS service.', $baseUrl, $provider, $configuredVoices);
        } catch (Throwable $exception) {
            $this->logFailure('health_failed', $exception->getMessage());

            return $this->dashboardFailure('unavailable', 'Kokoro voice status could not be checked.', $baseUrl, $provider, $configuredVoices);
        }

        if (! $response->successful()) {
            $this->logFailure('health_http_'.$response->status(), $response->body());

            return $this->dashboardFailure('unavailable', 'Kokoro TTS responded, but the health check failed.', $baseUrl, $provider, $configuredVoices);
        }

        $payload = $response->json();
        $connected = is_array($payload) && (bool) ($payload['ok'] ?? false);
        $voices = is_array($payload) ? ($payload['voices'] ?? $configuredVoices) : $configuredVoices;

        return [
            'enabled' => true,
            'connected' => $connected,
            'status' => $connected ? 'connected' : 'unavailable',
            'label' => $connected ? 'Kokoro Voice Connected' : 'Kokoro Voice Needs Attention',
            'message' => $connected
                ? 'Natural Kokoro voice is available for Miss Vivian, Miss Ciel, and Miss Estelle.'
                : 'Kokoro TTS health check did not report ready status.',
            'base_url' => $baseUrl,
            'provider' => $payload['provider'] ?? $provider,
            'service' => $payload['service'] ?? 'readirect-tts',
            'voices' => $voices,
            'configured_voices' => $configuredVoices,
        ];
    }

    public function pathForCacheKey(string $cacheKey): ?string
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/', $cacheKey)) {
            return null;
        }

        $path = self::CACHE_DIR.'/'.$cacheKey.'.wav';

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->path($path);
    }

    private function canonicalAgent(string $agent): string
    {
        return match ($agent) {
            'coach_feedback', AgentIdentity::MISS_CIEL, 'miss_ciel' => 'miss_ciel',
            'evaluator', 'evaluator_recommendation', AgentIdentity::MISS_ESTELLE, 'miss_estelle' => 'miss_estelle',
            default => 'miss_vivian',
        };
    }

    private function sanitizeText(string $text): string
    {
        $cleaned = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));
        $cleaned = str_replace(['`', '{', '}'], '', $cleaned);

        return Str::limit($cleaned, 300, '');
    }

    private function sanitizeKey(mixed $value, int $limit): ?string
    {
        $cleaned = trim((string) $value);
        if ($cleaned === '') {
            return null;
        }

        $cleaned = preg_replace('/[^A-Za-z0-9_.-]/', '', $cleaned) ?: '';

        return $cleaned === '' ? null : Str::limit($cleaned, $limit, '');
    }

    private function cacheKey(array $profile, string $text, ?string $intent = null, ?string $lineKey = null): string
    {
        return hash('sha256', implode('|', [
            config('readirect.tts.provider', 'kokoro'),
            config('readirect.tts.engine', 'kokoro'),
            (bool) config('readirect.tts.expressive.enabled', false) ? 'expressive:on' : 'expressive:off',
            config('readirect.tts.expressive.engine', 'index_tts2'),
            config('readirect.tts.humanization.cache_version', 'humanized-v1'),
            (bool) config('readirect.tts.humanization.auto_prompt_extension_enabled', false) ? 'auto_extend:on' : 'auto_extend:off',
            (bool) config('readirect.tts.humanization.curated_prompts_enabled', true) ? 'curated:on' : 'curated:off',
            'curated_target:'.number_format((float) config('readirect.tts.humanization.curated_prompt_target_seconds', 7.0), 1, '.', ''),
            'reference_weighting:'.((bool) config('readirect.tts.expressive.reference_weighting_enabled', true) ? 'on' : 'off'),
            (bool) config('readirect.tts.humanization.text_humanizer_enabled', true) ? 'humanize:on' : 'humanize:off',
            (bool) config('readirect.tts.humanization.delivery_control_enabled', true) ? 'delivery:on' : 'delivery:off',
            (bool) config('readirect.tts.humanization.audio_humanizer_enabled', true) ? 'audio:on' : 'audio:off',
            (bool) config('readirect.tts.humanization.pause_control_enabled', true) ? 'pause:on' : 'pause:off',
            $profile['agent'],
            $profile['voice'],
            number_format((float) $profile['speed'], 2, '.', ''),
            $intent ?: 'intent:none',
            $lineKey ?: 'line_key:none',
            $text,
        ]));
    }

    private function fallbackPayload(array $profile, string $text): array
    {
        return [
            'agent' => $profile['agent'],
            'display_name' => $profile['display_name'],
            'text' => $text,
            'voice_enabled' => false,
            'tts_provider' => null,
            'audio_url' => null,
            'fallback' => true,
            'text_fallback_allowed' => false,
        ];
    }

    private function successPayload(array $profile, string $text, string $cacheKey, bool $includeDebug, bool $cacheHit, int $latency): array
    {
        $payload = [
            'agent' => $profile['agent'],
            'display_name' => $profile['display_name'],
            'text' => $text,
            'voice_enabled' => true,
            'tts_provider' => config('readirect.tts.provider', 'kokoro'),
            'audio_url' => route('agent-voice.show', ['cacheKey' => $cacheKey], false),
            'fallback' => false,
            'text_fallback_allowed' => true,
        ];

        return $this->withDebug($payload, $includeDebug, null, $latency, $profile['voice'], $cacheHit);
    }

    private function withDebug(array $payload, bool $includeDebug, ?string $fallbackReason, ?int $latency, ?string $voice = null, ?bool $cacheHit = null): array
    {
        if (! $includeDebug) {
            return $payload;
        }

        $payload['debug'] = [
            'provider' => $payload['tts_provider'],
            'voice' => $voice,
            'cache_hit' => $cacheHit,
            'fallback_reason' => $fallbackReason,
            'latency_ms' => $latency,
        ];

        return $payload;
    }

    private function latency(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    private function speedForAgent(string $agent): float
    {
        $speed = (float) config('readirect.tts.speeds.'.$agent);
        $range = config('readirect.tts.speed_ranges.'.$agent, []);
        $minimum = (float) ($range['min'] ?? $speed);
        $maximum = (float) ($range['max'] ?? $speed);

        return round(max($minimum, min($maximum, $speed)), 2);
    }

    private function voiceForAgent(string $agent): string
    {
        return match ($agent) {
            'miss_ciel' => 'af_heart',
            'miss_vivian' => 'af_bella',
            'miss_estelle' => str_contains(strtolower((string) config('readirect.tts.voices.miss_estelle', 'bf_isabella')), 'isabella')
                ? (string) config('readirect.tts.voices.miss_estelle', 'bf_isabella')
                : 'bf_isabella',
            default => 'af_bella',
        };
    }

    private function logFailure(string $reason, ?string $detail): void
    {
        $context = ['reason' => $reason];

        if ((bool) config('readirect.tts.debug') && $detail) {
            $context['detail'] = Str::limit($detail, 500);
        }

        Log::warning('Agent TTS fell back to text-only message.', $context);
    }

    private function dashboardFailure(string $status, string $message, string $baseUrl, string $provider, array $configuredVoices): array
    {
        return [
            'enabled' => true,
            'connected' => false,
            'status' => $status,
            'label' => 'Kokoro Voice Disconnected',
            'message' => $message,
            'base_url' => $baseUrl,
            'provider' => $provider,
            'voices' => $configuredVoices,
        ];
    }
}
